#!/usr/bin/perl -w 


my $wgetPath = "/usr/bin/wget";



use Digest::MD5 qw(md5 md5_hex md5_base64);



#my $value = md5_hex( "test" );


use HTTP::Request::Common qw(POST);
use LWP::UserAgent;
my $ua = new LWP::UserAgent;



my $reportName = "sync_report.txt";


my $url = "";
my $password = "";


if( not -e "NOTE_DATA" ) {
	# fresh checkout?

	my $numArgs = $#ARGV + 1;

	if( $numArgs != 1 ) {
		    print "\n";
			print "No NOTE_DATA directory found\n\n";
			print "Checkout usage:\n";
			print "  noteSync.pl server_url\n\n";
			print "Example:\n";
			print "  noteSync.pl http://test.com/noteServer/server.php\n";
			exit 1;
		}

	
	$url = $ARGV[0];

	
	print "Enter password: ";
    # flush
	$| = 1;
	
	$_ = <STDIN>;

	#trim newline
	chomp;

	$password = $_;


	#my $req = POST "$url",
	#[ password => "$password", action => "get_note_list" ];



	#my $result = $ua->request( $req );

	my $content = serverPost( [ action => "get_note_list" ] );
	
	mkdir( "NOTE_DATA", oct( "0777" ) );
	
	writeDataFile( "server.url", $url );
	writeDataFile( "server.password", $password ); 
}
else {
	# existing checkout

	if( !doesDataFileExist( "server.url" ) || 
		!doesDataFileExist( "server.password" ) ) {
		print "Checkout directory NOTE_DATA is missing files\n";
		exit 1;
	}

    $url = readDataFile( "server.url" );
    $password = readDataFile( "server.password" );
}


print "Syncing with server '$url'...\n";



#########
# Step 1:  Check for locally updated notes and send them to server
#########


my @nameFiles = glob "NOTE_DATA/*.name";
foreach $nameFile ( @nameFiles ) {
	# these map UIs to names
	$noteFileName = readFile( $nameFile );

    $uid = $nameFile;

    $uid =~ s/\.name$//;

    $uid =~ s/NOTE_DATA\///;

    $hashFileName = $noteFileName . ".hash";

	
	if( !doesFileExist( $noteFileName ) ) {
		# file missing

		# clear hash so that it will be fetched for sure in update step below
		
		writeDataFile( $hashFileName, "file_missing" );
	}
    elsif( dataFileModTime( $hashFileName ) < fileModTime( $noteFileName ) ) {
		
		$noteFileContents = readFile( $noteFileName );
		
		$oldHash = readDataFile( $hashFileName );
		$newHash = md5_hex( $noteFileContents );

		if( $oldHash ne $newHash ) {
			print "-- $noteFileName  changed locally\n";
			

			$serverHash = serverPost( [ action => "update_note",
										uid => $uid,
										body_text => $noteFileContents ] );

			if( $serverHash ne $newHash ) {
				print "hash received from server ($serverHash) doesn't match".
					" our local hash\n";
				die();
			}
			
			# else save new hash
			writeDataFile( $hashFileName, $newHash );
		}
	}	
}


#########
# Step 2:  Fetch current note list from server (UIDs and hashes)
#########
my $serverNoteList = serverPost( [ action => "get_note_list" ] );

my @noteArray = split( /\n/, $serverNoteList );


#########
# Step 3:  Check for any new notes on server that we don't have.  
#          Create local files with unique names.
# Step 4:  Checks for any updated notes on the server.  Updates local files.
#########

foreach my $noteInfo ( @noteArray ) {
	
	( my $uid, my $hash ) = split( /\s+/ , $noteInfo );
	
	
	$nameFile = $uid . ".name";
	
	if( !doesDataFileExist( $nameFile ) ) {

		# pick a unique local name

		# try uid.txt first

		$localName = sprintf( "%06d", $uid ) . ".txt";

		while( doesFileExist( $localName ) ) {
			
			# try making it more unique
			
			$localName = "1_" . $localName;
		}

		print "-- $localName is new on server ($uid)\n";

		
		writeDataFile( $localName . ".uid", $uid );
		writeDataFile( $localName . ".hash", $hash );
		
		writeDataFile( $uid . ".name", $localName );

		$noteContents = serverPost( [ action => "get_note",
									  uid => $uid ] );
		
		writeFile( $localName, $noteContents );
	}
	else {
		# file already exists

		# updated on server?

		$localName = readDataFile( $uid . ".name" );
		
		$localHash = readDataFile( $localName . ".hash" );

		if( $localHash ne $hash ) {
			print "-- $localName  changed on server\n";

			writeDataFile( $localName . ".hash", $hash );
		
			$noteContents = serverPost( [ action => "get_note",
										  uid => $uid ] );
		
			writeFile( $localName, $noteContents );
		}
	}
}


#########
# Step 5:  Checks for any local files that aren't on the server yet.  
#          Posts them and recieves UID and hash info back to save locally.
#########
my @localNotes = glob "*.txt";
foreach $localNote ( @localNotes ) {

	
	if( $localNote ne $reportName and
		! doesDataFileExist( $localNote . ".uid" ) ) {
		
		print "-- $localNote  is new locally\n";

		$noteFileContents = readFile( $localNote );

		$serverResult = serverPost( [ action => "add_note",
									  body_text => $noteFileContents ] );
		
		( my $uid, my $hash ) = split( /\s+/ , $serverResult );

		
		writeDataFile( $uid . ".name", $localNote );
		writeDataFile( $localNote . ".uid", $uid );
		writeDataFile( $localNote . ".hash", $hash );
	}

}


print "\nSync done.\n\n";



#########
# Finally:  Print server-side report.
#########



print "Writing server state report to '$reportName'...\n";


open( REPORT_FILE, ">$reportName" ) or die;


$serverNoteList = serverPost( [ action => "get_note_list" ] );

@noteArray = split( /\n/, $serverNoteList );

print REPORT_FILE "Note set on server (most recently viewed first):\n\n\n";

foreach my $noteInfo ( @noteArray ) {
	
	( my $uid, my $hash ) = split( /\s+/ , $noteInfo );
	
	
	$nameFile = $uid . ".name";
	
	if( doesDataFileExist( $nameFile ) ) {
		$localNote = readDataFile( $nameFile );
		print REPORT_FILE "$localNote\n";
		print REPORT_FILE "===========\n";
		

		$noteContents = readFile( $localNote );
		
		$noteContents =~ s/^\s+//;
		$noteContents =~ s/\s+$//;

		@noteLines = split( /\n/, $noteContents );
		
		if( scalar( @noteLines ) > 0 ) {
	 
			# first, a note title line
			$line = $noteLines[0];
			
			# trim whitespace from end, not just chomp (because it might
			# have non-standard line ends like \r) 
			$line =~ s/\s+$//;
				
			if( length( $line ) > 60 ) {
				$line = substr( $line, 0, 57 ) . "...";
			}
			
			print REPORT_FILE "-- " . $line . " --\n\n"; 


			# now print a note summary
			$noteSummary = $noteContents;

			# remove DOS line ends
			$noteSummary =~ s/\r//g;

			# replace paragraph breaks with "  "
			$noteSummary =~ s/\n+/  /g;

			# remove big blobs of whitespace
			$noteSummary =~ s/\s\s+/  /g;

			# limit to 250 characters
			if( length( $noteSummary ) > 250 ) {
				$noteSummary = substr( $noteSummary, 0, 247 ) . "...";
			}
			
			print REPORT_FILE $noteSummary; 			
		}		
		print REPORT_FILE "\n\n\n\n\n";

	}
}

print "Report done.\n\n";




#use Data::Dumper;


##
# Makes a web POST request to the current URL as defined in the global $url.
# and the password defined in the global $password.
#
# @param0 the form variables as an array of name => value pairs
#
# @return the contents of the response.
#
# Example:
# my $contents = serverPost( [ action => "get_note", 
#							   uid => $uid ] );
##
sub serverPost {
	my $hashRef = $_[0];

	push( @$hashRef, password => $password );



	my $req = POST "$url", $hashRef;

	# prevent use of libwww-perl User-Agent header, which is Forbidden
	# on some web servers
	$ua->agent("");

	my $result = $ua->request( $req );

	if( $result->is_success ) {
		
		if( $result->content ne "REJECTED" ) {
			return $result->content;
		}
		else {
			print "Server at '$url' rejected our request.\n";
			exit 1;
		}
	}
	else {
		print "Posting to server '$url' failed.\n";
		exit 1;
	}
}



##
# Writes a string to a file.
#
# @param0 the name of the file.
# @param1 the string to print.
#
# Example:
# writeFile( "myFile.txt", "the new contents of this file" );
##
sub writeFile {
    my $fileName = $_[0];
    my $stringToPrint = $_[1];
    
    open( FILE, ">$fileName" ) or die;
    flock( FILE, 2 ) or die;

    print FILE $stringToPrint;
        
    close FILE;
}




##
# Reads file as a string.
#
# @param0 the name of the file.
#
# @return the file contents as a string.
#
# Example:
# my $value = readFile( "myFile.txt" );
##
sub readFile {
    my $fileName = $_[0];
    open( FILE, "$fileName" ) or die;
    flock( FILE, 1 ) or die;

    # read the entire file, set the <> separator to nothing
    local $/;

    my $value = <FILE>;
    close FILE;

    return $value;
}



##
# Checks if a file exists.
#
# @param0 the name of the file.
#
# @return 1 if it exists, and 0 otherwise.
#
# Example:
# $exists = doesFileExist( "myFile.txt" );
##
sub doesFileExist {
    my $fileName = $_[0];
    if( -e $fileName ) {
        return 1;
    }
    else {
        return 0;
    }
}


##
# Gets integer mod time for a file
#
# @param0 the name of the file.
#
# @return mod time in seconds since the epoch.
#
# Example:
# $modTime = fileModTime( "myFile.txt" );
##
sub fileModTime {
	my $fileName = $_[0];
	
	return ( stat( $fileName ) )[9];
}




# versions of the above functions that apply to the NOTE_DATA subdirectory
# automatically

sub writeDataFile {
	my $fileName = $_[0];
    my $stringToPrint = $_[1];

	writeFile( "NOTE_DATA/" . $fileName, $stringToPrint );
}


sub readDataFile {
	my $fileName = $_[0];
    
	return readFile( "NOTE_DATA/" . $fileName );
}



sub doesDataFileExist {
	my $fileName = $_[0];

	return doesFileExist( "NOTE_DATA/" . $fileName );
}



sub dataFileModTime {
	my $fileName = $_[0];

	return fileModTime( "NOTE_DATA/" . $fileName );
}





	
