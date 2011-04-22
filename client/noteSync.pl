#!/usr/bin/perl -w 


my $wgetPath = "/usr/bin/wget";



use Digest::MD5 qw(md5 md5_hex md5_base64);



#my $value = md5_hex( "test" );


use HTTP::Request::Common qw(POST);
use LWP::UserAgent;
my $ua = new LWP::UserAgent;




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
			print "  bulk.pl http://test.com/noteServer/server.php\n";
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


    if( $content ne "REJECTED" ) {
		mkdir( "NOTE_DATA", oct( "0777" ) );

		writeDataFile( "server.url", $url );
		writeDataFile( "server.password", $password ); 
	}
	else {
		print "Server at '$url' rejected password.\n";
		exit 1;
	}
	
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

	my $result = $ua->request( $req );

	if( $result->is_success ) {
	
		return $result->content;
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
# my $value = readFileValue( "myFile.txt" );
##
sub readFileValue {
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



# versions of the above functions that apply to the NOTE_DATA subdirectory
# automatically

sub writeDataFile {
	my $fileName = $_[0];
    my $stringToPrint = $_[1];

	writeFile( "NOTE_DATA/" . $fileName, $stringToPrint );
}


sub readDataFile {
	my $fileName = $_[0];
    
	return readFileValue( "NOTE_DATA/" . $fileName );
}



sub doesDataFileExist {
	my $fileName = $_[0];

	return doesFileExist( "NOTE_DATA/" . $fileName );
}
