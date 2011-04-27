<?php



global $ns_version;
$ns_version = "1";



// edit settings.php to change server' settings
include( "settings.php" );




// no end-user settings below this point




// enable verbose error reporting to detect uninitialized variables
error_reporting( E_ALL );



// page layout for web-based setup
$setup_header = "
<HTML>
<HEAD><TITLE>Note Server Web-based setup</TITLE></HEAD>
<BODY BGCOLOR=#FFFFFF TEXT=#000000 LINK=#0000FF VLINK=#FF0000>

<CENTER>
<TABLE WIDTH=75% BORDER=0 CELLSPACING=0 CELLPADDING=1>
<TR><TD BGCOLOR=#000000>
<TABLE WIDTH=100% BORDER=0 CELLSPACING=0 CELLPADDING=10>
<TR><TD BGCOLOR=#EEEEEE>";

$setup_footer = "
</TD></TR></TABLE>
</TD></TR></TABLE>
</CENTER>
</BODY></HTML>";





// ensure that magic quotes are on (adding slashes before quotes
// so that user-submitted data can be safely submitted in DB queries)
if( !get_magic_quotes_gpc() ) {
    // force magic quotes to be added
    $_GET     = array_map( 'ns_addslashes_deep', $_GET );
    $_POST    = array_map( 'ns_addslashes_deep', $_POST );
    $_REQUEST = array_map( 'ns_addslashes_deep', $_REQUEST );
    $_COOKIE  = array_map( 'ns_addslashes_deep', $_COOKIE );
    }
    





// all calls need to connect to DB, so do it once here
ns_connectToDatabase();

// close connection down below (before function declarations)


// testing:
//sleep( 5 );


// general processing whenver server.php is accessed directly





// grab POST/GET variables
$action = "";
if( isset( $_REQUEST[ "action" ] ) ) {
    $action = $_REQUEST[ "action" ];
    }

$debug = "";
if( isset( $_REQUEST[ "debug" ] ) ) {
    $debug = $_REQUEST[ "debug" ];
    }


$remoteIP = "";
if( isset( $_SERVER[ "REMOTE_ADDR" ] ) ) {
    $remoteIP = $_SERVER[ "REMOTE_ADDR" ];
    }



if( $action == "version" ) {
    global $ns_version;
    echo "$ns_version";
    }
else if( $action == "show_log" ) {
    ns_showLog();
    }
else if( $action == "clear_log" ) {
    ns_clearLog();
    }

// protocol implementations
else if( $action == "get_note_list" ) {
    ns_getNoteList();
    }
else if( $action == "get_note" ) {
    ns_getNote();
    }
else if( $action == "add_note" ) {
    ns_addNote();
    }
else if( $action == "update_note" ) {
    ns_updateNote();
    }

// web-ui implementations
else if( $action == "list_notes" ) {
    ns_listNotes();
    }
else if( $action == "view_note" ) {
    ns_viewNote();
    }
else if( $action == "edit_note" ) {
    ns_editNote();
    }
else if( $action == "new_note" ) {
    ns_newNote();
    }

// setup
else if( $action == "ns_setup" ) {
    global $setup_header, $setup_footer;
    echo $setup_header; 

    echo "<H2>Note Server Web-based Setup</H2>";

    echo "Creating tables:<BR>";

    echo "<CENTER><TABLE BORDER=0 CELLSPACING=0 CELLPADDING=1>
          <TR><TD BGCOLOR=#000000>
          <TABLE BORDER=0 CELLSPACING=0 CELLPADDING=5>
          <TR><TD BGCOLOR=#FFFFFF>";

    ns_setupDatabase();

    echo "</TD></TR></TABLE></TD></TR></TABLE></CENTER><BR><BR>";
    
    echo $setup_footer;
    }
else if( preg_match( "/server\.php/", $_SERVER[ "SCRIPT_NAME" ] ) ) {
    // server.php has been called without an action parameter

    // the preg_match ensures that server.php was called directly and
    // not just included by another script
    
    // quick (and incomplete) test to see if we should show instructions
    global $tableNamePrefix;
    
    // check if our "games" table exists
    $tableName = $tableNamePrefix . "games";
    
    $exists = ns_doesTableExist( $tableName );
        
    if( $exists  ) {
        echo "Note Server database setup and ready";
        }
    else {
        // start the setup procedure

        global $setup_header, $setup_footer;
        echo $setup_header; 

        echo "<H2>Note Server Web-based Setup</H2>";
    
        echo "Note Server will walk you through a " .
            "brief setup process.<BR><BR>";
        
        echo "Step 1: ".
            "<A HREF=\"server.php?action=ns_setup\">".
            "create the database tables</A>";

        echo $setup_footer;
        }
    }



// done processing
// only function declarations below

ns_closeDatabase();







/**
 * Creates the database tables needed by seedBlogs.
 */
function ns_setupDatabase() {
    global $tableNamePrefix;

    
    $tableName = $tableNamePrefix . "log";
    if( ! ns_doesTableExist( $tableName ) ) {

        // this table contains general info about the server
        // use INNODB engine so table can be locked
        $query =
            "CREATE TABLE $tableName(" .
            "entry TEXT NOT NULL, ".
            "entry_time DATETIME NOT NULL );";

        $result = ns_queryDatabase( $query );

        echo "<B>$tableName</B> table created<BR>";
        }
    else {
        echo "<B>$tableName</B> table already exists<BR>";
        }

    
    
    $tableName = $tableNamePrefix . "notes";
    if( ! ns_doesTableExist( $tableName ) ) {

        // this table contains the notes
        $query =
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";

        $result = ns_queryDatabase( $query );

        echo "<B>$tableName</B> table created<BR>";
        }
    else {
        echo "<B>$tableName</B> table already exists<BR>";
        }
    }



function ns_showLog() {
    $password = ns_checkPassword( "show_log" );

    global $header, $footer;
    
    eval( $header );
    
    ns_menuBar( $password, "" );
    
    global $tableNamePrefix;
    
    $query = "SELECT * FROM $tableNamePrefix"."log;";
    $result = ns_queryDatabase( $query );
    
    $numRows = mysql_numrows( $result );

    echo "<a href=\"server.php?action=clear_log&password=$password\">".
        "Clear log</a>";
        
    echo "<hr>";
    
    echo "$numRows log entries:<br><br><br>\n";
    
    
    for( $i=0; $i<$numRows; $i++ ) {
        $time = mysql_result( $result, $i, "entry_time" );
        $entry = mysql_result( $result, $i, "entry" );
        
        echo "<b>$time</b>:<br>$entry<hr>\n";
        }
    
    eval( $footer );
    }



function ns_clearLog() {

    $password = ns_checkPassword( "clear_log" );


    global $header, $footer;
    
    eval( $header );
    ns_menuBar( $password, "" );
    
    global $tableNamePrefix;

    $query = "DELETE FROM $tableNamePrefix"."log;";
    $result = ns_queryDatabase( $query );
    
    if( $result ) {
        echo "Log cleared.";
        }
    else {
        echo "DELETE operation failed?";
        }

    eval( $footer );
    }






function ns_placeFlag() {
    $level_number = "";
    if( isset( $_REQUEST[ "level_number" ] ) ) {
        $level_number = $_REQUEST[ "level_number" ];
        }
    $level_seed = "";
    if( isset( $_REQUEST[ "level_seed" ] ) ) {
        $level_seed = $_REQUEST[ "level_seed" ];
        }
    $spot = "";
    if( isset( $_REQUEST[ "spot" ] ) ) {
        $spot = $_REQUEST[ "spot" ];
        }
    $flag = "";
    if( isset( $_REQUEST[ "flag" ] ) ) {
        $flag = $_REQUEST[ "flag" ];
        }
    $sig = "";
    if( isset( $_REQUEST[ "sig" ] ) ) {
        $sig = $_REQUEST[ "sig" ];
        }

    $sig = strtoupper( $sig );


    if( strlen( $flag ) != 9 ) {
        echo "REJECTED";
        return;
        }


    // verify sig
            
    global $sharedSecret;
            
    $trueSig =
        sha1( $level_number . $level_seed . $spot . $flag . $sharedSecret );
    
    $trueSig = strtoupper( $trueSig );


    if( $trueSig != $sig ) {
        echo "REJECTED";
        return;
        }
    
    // else sig good
    
    global $tableNamePrefix, $remoteIP;

    
    // disable autocommit so that FOR UPDATE actually works
    ns_queryDatabase( "SET AUTOCOMMIT = 0;" );
    

    /*
            "CREATE TABLE $tableName(" .
            "level_number INT NOT NULL," .
            "level_seed INT UNSIGNED NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "change_ip_address CHAR(15) NOT NULL," .
            "change_count INT UNSIGNED NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "view_count INT UNSIGNED NOT NULL," .
            "flag_a CHAR(9) NOT NULL," .
            "flag_b CHAR(9) NOT NULL," .
            "PRIMARY KEY( level_number, level_seed ) ) ENGINE = INNODB;";
    */

    
    $query = "SELECT * FROM $tableNamePrefix"."flags ".
        "WHERE level_number = '$level_number' AND level_seed = '$level_seed' ".
        "FOR UPDATE;";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    if( $numRows == 1 ) {
        $row = mysql_fetch_array( $result, MYSQL_ASSOC );
        
        $old_flag_a = $row[ "flag_a" ];

        if( $spot == "A" && $old_flag_a != "BLANKFLAG" ) {
            // already a permanent flag here

            // unlock rows that were locked by FOR UPDATE above
            ns_queryDatabase( "COMMIT;" );
            
            ns_queryDatabase( "SET AUTOCOMMIT = 1;" );

            echo "REJECTED";
            return;
            }

        $change_count = $row[ "change_count" ];
        $change_count ++;
        
        
        $flagClause;

        if( $spot == "A" ) {
            $flagClause = "flag_a = '$flag'";
            }
        else {
            $flagClause = "flag_b = '$flag'";
            }

        
        
        
        $query = "UPDATE $tableNamePrefix"."flags SET " .
            "change_date = CURRENT_TIMESTAMP, " .
            "change_ip_address = '$remoteIP', " .
            "change_count = '$change_count', " .
            "$flagClause " .
            "WHERE level_number = '$level_number' AND ".
            "level_seed = '$level_seed';";
        
        $result = ns_queryDatabase( $query );


        echo "OK";
        }
    else {
        // doesn't exist yet

        $flagClause;

        if( $spot == "A" ) {
            $flagClause = "'$flag', 'BLANKFLAG'"; 
           }
        else {
            $flagClause = "'BLANKFLAG', '$flag'";
            }
        
        $query = "INSERT INTO $tableNamePrefix". "flags VALUES ( " .
            "'$level_number', '$level_seed', ".
            "CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".
            "'$remoteIP', '1', CURRENT_TIMESTAMP, '0', ".
            "$flagClause );";

        $result = ns_queryDatabase( $query );

        echo "OK";
        }

    /*
http://localhost/jcr13/game10_flag/server.php?action=place_flag&level_number=5&level_seed=234890902&spot=A&flag=FA89&sig=ba48acc4f3603c16f5629404470dbad0eaaec7cd
     */

    // unlock rows that were locked by FOR UPDATE above
    ns_queryDatabase( "COMMIT;" );
    
    ns_queryDatabase( "SET AUTOCOMMIT = 1;" );
    }






function ns_getNoteList() {

    ns_checkPassword( "get_note" );

    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    
    $query = "SELECT uid, hash FROM $tableNamePrefix"."notes ".
        "ORDER BY view_date DESC";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );


    for( $i=0; $i<$numRows; $i++ ) {
        $uid = mysql_result( $result, $i, "uid" );
        $hash = mysql_result( $result, $i, "hash" );

        echo "$uid $hash\n";
        }
    }



function ns_getNote() {

    ns_checkPassword( "get_note" );
    
    $uid = "";
    if( isset( $_REQUEST[ "uid" ] ) ) {
        $uid = $_REQUEST[ "uid" ];
        }
    else {
        echo "REJECTED";
        die();
        }
    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    
    $query = "SELECT body_text FROM $tableNamePrefix"."notes ".
        "WHERE uid = '$uid'";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );


    if( $numRows == 1 ) {
        $body_text = mysql_result( $result, 0, "body_text" );
        echo "$body_text";
        }
    else {
        echo "REJECTED";
        }
    }


function getTitleLine( $inBodyText ) {
    $pieces = explode("\n", trim( htmlspecialchars( $inBodyText ) ), 2 );

    $title_line = "";
    
    if( count( $pieces ) > 0 ) {
        
        $title_line = $pieces[0];
        }

    if( strlen( $title_line ) > 60 ) {
        // trim it more
        
        $title_line = substr( $title_line, 0, 57 );
        $title_line = $title_line . "...";
        }
    return $title_line;
    }





function ns_addNote() {

    ns_checkPassword( "add_note" );
    
    $body_text = "";
    if( isset( $_REQUEST[ "body_text" ] ) ) {
        $body_text = $_REQUEST[ "body_text" ];
        }
    else {
        echo "REJECTED";
        ns_log( "addNote failed due to missing body_text" );
        die();
        }


    // convert incoming dos line ends to unix
    $body_text = preg_replace( "/\r/", "", $body_text );
        
    
    $from_web = 0;
    if( isset( $_REQUEST[ "from_web" ] ) ) {
        $from_web = $_REQUEST[ "from_web" ];
        }

    
    $hash = md5( $body_text );

    
    $title_line = getTitleLine( $body_text );
    
    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    // uid is created by auto-increment
    $query = "INSERT INTO $tableNamePrefix". "notes ".
        "( hash, creation_date,  change_date, view_date, ".
        "  title_line, body_text ) ".
        "VALUES ( " .
        "'$hash', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".
        "CURRENT_TIMESTAMP, '$title_line', ".
        "'$body_text' );";

    $result = ns_queryDatabase( $query );

    $uid = mysql_insert_id();


    if( !$from_web ) {
        echo "$uid $hash";
        }
    else {
        ns_listNotes();
        }
    }




function ns_updateNote() {

    ns_checkPassword( "update_note" );
    
    $uid = "";
    if( isset( $_REQUEST[ "uid" ] ) ) {
        $uid = $_REQUEST[ "uid" ];
        }
    else {
        echo "REJECTED";
        ns_log( "updateNote failed due to missing uid" );
        die();
        }

    $body_text = "";
    if( isset( $_REQUEST[ "body_text" ] ) ) {
        $body_text = $_REQUEST[ "body_text" ];
        }
    else {
        echo "REJECTED";
        ns_log( "updateNote failed due to missing body_text" );
        die();
        }

    // convert incoming dos line ends to unix
    $body_text = preg_replace( "/\r/", "", $body_text );


    $from_web = 0;
    if( isset( $_REQUEST[ "from_web" ] ) ) {
        $from_web = $_REQUEST[ "from_web" ];
        }
    
    $hash = md5( $body_text );


    $title_line = getTitleLine( $body_text );
        
    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */
    
    // uid is created by auto-increment
    $query = "UPDATE $tableNamePrefix". "notes SET ".
        "hash = '$hash', change_date = CURRENT_TIMESTAMP, ".
        "view_date = CURRENT_TIMESTAMP, title_line = '$title_line', ".
        "body_text = '$body_text' WHERE uid = '$uid';";

    $result = ns_queryDatabase( $query );

    if( mysql_affected_rows() == 1 ) {

        if( !$from_web ) {
            echo "$hash";
            }
        else {
            ns_listNotes();
            }
        }
    else {
        echo "REJECTED";
        ns_log( "updateNote failed because query for uid $uid not found" );
        }
    
    }



function dateFormat( $inMysqlDate ) {
    $dateStamp = strtotime( $inMysqlDate );
    
    // format as in    Sunday, July 7, 2005 [4:52 pm]
    $dateString = date( "l, F j, Y [g:i a]", $dateStamp );
    
    return $dateString;
    }



function ns_menuBar( $password, $search ) {

    $order_by = "view_date";
    if( isset( $_REQUEST[ "order_by" ] ) ) {
        $order_by = $_REQUEST[ "order_by" ];
        }
    ?>
            <FORM ACTION="server.php" METHOD="post">
<?php
    echo "[<a href=\"server.php?action=list_notes&password=$password" .
        "&order_by=$order_by\">Main</a>] --- ";
    echo "[<a href=\"server.php?action=new_note&password=$password" .
        "&order_by=$order_by\">New</a>] --- ";
    
    // form for searching notes
?>
        
    <INPUT TYPE="hidden" NAME="password" VALUE="<?php echo $password;?>">
    <INPUT TYPE="hidden" NAME="action" VALUE="list_notes">
    <INPUT TYPE="hidden" NAME="order_by" VALUE="<?php echo $order_by;?>">
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="search"
             VALUE="<?php echo $search;?>">
    <INPUT TYPE="Submit" VALUE="Search">
    </FORM>
<?php

    echo "<br><br>";

    }




function ns_listNotes() {
    // call several of these global so they can be accessed properly
    // inside the sub-functions we define below
    global $password, $skip, $search, $order_by;
    
    
    $password = ns_checkPassword( "list_notes" );

    global $tableNamePrefix, $remoteIP;    




    $skip = 0;
    if( isset( $_REQUEST[ "skip" ] ) ) {
        $skip = $_REQUEST[ "skip" ];
        }

    $order_by = "view_date";
    if( isset( $_REQUEST[ "order_by" ] ) ) {
        $order_by = $_REQUEST[ "order_by" ];
        }

    global $notesPerPage;    


    $search = "";
    if( isset( $_REQUEST[ "search" ] ) ) {
        $search = $_REQUEST[ "search" ];
        }

    $keywordClause = "";
    $searchDisplay = "";
    
    if( $search != "" ) {
        
        $keywordClause = "WHERE ( body_text LIKE '%$search%' " .
            "OR uid LIKE '%$search%' ) ";

        $searchDisplay = " matching <b>$search</b>";
        }


    

    // first, count results
    $query = "SELECT COUNT(*) FROM $tableNamePrefix"."notes $keywordClause;";

    $result = ns_queryDatabase( $query );
    $totalNotes = mysql_result( $result, 0, 0 );

    
             
    $query = "SELECT * FROM $tableNamePrefix"."notes $keywordClause".
        "ORDER BY $order_by DESC ".
        "LIMIT $skip, $notesPerPage;";
    $result = ns_queryDatabase( $query );
    
    $numRows = mysql_numrows( $result );

    $startSkip = $skip + 1;
    
    $endSkip = $startSkip + $notesPerPage - 1;

    if( $endSkip > $totalNotes ) {
        $endSkip = $totalNotes;
        }
    $showingDisplay = " (showing $startSkip - $endSkip)";
    if( $totalNotes <= 1 ) {
        $showingDisplay = "";
        }
    

    global $header, $footer;
    
    eval( $header );
    ns_menuBar( $password, $search );


    $recordWord = "notes";

    if( $totalNotes == 1 ) {
        $recordWord = "note";
        }
    
    echo "<center>$totalNotes $recordWord" .$searchDisplay .
        "$showingDisplay</center>";
    

    
    $nextSkip = $skip + $notesPerPage;

    $prevSkip = $skip - $notesPerPage;

    // use output buffering to capture these widgets so we can
    // repeat them at the end of the list
    ob_start();

    
    echo "<table border=0 width=100%><tr>";

    echo "<td align=left>";
    
    if( $prevSkip >= 0 ) {
        echo "[<a href=\"server.php?action=list_notes&password=$password" .
            "&skip=$prevSkip&search=$search".
            "&order_by=$order_by\">Previous Page</a>] ";
        }
    if( $nextSkip < $totalNotes ) {
        echo "[<a href=\"server.php?action=list_notes&password=$password" .
            "&skip=$nextSkip&search=$search".
            "&order_by=$order_by\">Next Page</a>]";
        }

    echo "</td><td align=right>";

    
    function orderLink( $inOrderBy, $inLinkText ) {
        global $password, $skip, $search, $order_by;
        if( $inOrderBy == $order_by ) {
            // already displaying this order, don't show link
            return "<b>$inLinkText</b>";
            }

        // else show a link to switch to this order
        return "<a href=\"server.php?action=list_notes&password=$password" .
            "&search=$search&skip=$skip&order_by=$inOrderBy\">$inLinkText</a>";
        }


    echo "Stack[ ";
    echo orderLink( "creation_date", "Created" )." | ";
    echo orderLink( "change_date", "Changed" )." | ";
    echo orderLink( "view_date", "Viewed" )."";
    echo " ]";

    echo "</td></tr></table>";

    $pageAndSortWidgets = ob_get_contents();
    ob_end_clean();


    echo $pageAndSortWidgets;
    
    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    
    echo "";
    
    echo "<table border=0 cellpadding=5 cellspacing=0>\n";


    



    
    

    for( $i=0; $i<$numRows; $i++ ) {
        $uid = mysql_result( $result, $i, "uid" );
        $body_text = mysql_result( $result, $i, "body_text" );
        $title_line = mysql_result( $result, $i, "title_line" );
        $creation_date = mysql_result( $result, $i, "creation_date" );
        $change_date = mysql_result( $result, $i, "change_date" );
        $view_date = mysql_result( $result, $i, "view_date" );
        
        $creationString = dateFormat( $creation_date );
        $changeString = dateFormat( $change_date );
        $viewString = dateFormat( $view_date );
            
        echo "<tr bgcolor=#CCCCCC>\n";
        echo "<td><font size=6>".
            "<a href=\"server.php?action=view_note&uid=$uid&".
            "password=$password&order_by=$order_by\">".
            "$title_line</a></font></td>\n";
        echo "<td align=right>".
            "[<a href=\"server.php?action=edit_note&uid=$uid&".
            "password=$password&order_by=$order_by\">Edit</a>]</td>\n";
        echo "</tr>\n";

        $snippet = trim( $body_text );
        
        if( strlen( $snippet ) > 250 ) {
            // trim it to a snippet
        
            $snippet = trim( substr( $snippet, 0, 250 ) );
            $snippet = $snippet . "...";
        }
        $snippet = htmlspecialchars( $snippet );
                
        echo "<tr>\n";
        echo "<td colspan=2>$snippet<br><br><br><br><br></td>\n";
        echo "</tr>\n";
        
        }
    echo "</table>";

    echo $pageAndSortWidgets;

    


    
    echo "<br><table border=0 width=100%><tr><td align=right>";
    echo "<a href=\"server.php?action=show_log&password=$password\">".
        "Log</a>";
    echo "</td></tr></table>";
        
    eval( $footer );
    }



function ns_viewNote() {

    $password = ns_checkPassword( "view_note" );
    
    $uid = "";
    if( isset( $_REQUEST[ "uid" ] ) ) {
        $uid = $_REQUEST[ "uid" ];
        }
    else {
        echo "REJECTED";
        die();
        }
    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    
    $query = "SELECT body_text, creation_date, change_date ".
        "FROM $tableNamePrefix"."notes ".
        "WHERE uid = '$uid'";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );



    
    if( $numRows != 1 ) {
        echo "REJECTED";
        return;
        }

    
    $body_text = mysql_result( $result, 0, "body_text" );
    $created = dateFormat( mysql_result( $result, 0, "creation_date" ) );
    $changed = dateFormat( mysql_result( $result, 0, "change_date" ) );


    $formattedText = preg_replace( "/\n\s*/", "<br><br>",
                                   htmlspecialchars( $body_text ) );

    $formattedText =
        preg_replace( "/(http:\/\/\S+)/", "<a href=\"$1\">$1</a>", $formattedText );
    
    global $header, $footer;
    
    eval( $header );

    ns_menuBar( $password, "" );
    

    echo "[<a href=\"server.php?action=edit_note&uid=$uid&password=$password" .
        "\">Edit</a>]<br>";

    echo "<table border=0 width=100% cellpadding=10>".
        "<tr><td bgcolor='#EEEEEE'>";
    echo $formattedText;
    echo "</td></tr></table>";

    echo "<br><table border=0 width=100%><tr>";
    echo "<td align=left>Created | $created</td>";
    echo "<td align=right>Changed | $changed</td>";
    echo "</tr></table>";
    

                           
    eval( $footer );

    // viewed
    $query = "UPDATE $tableNamePrefix". "notes SET ".
        "view_date = CURRENT_TIMESTAMP WHERE uid = '$uid';";

    $result = ns_queryDatabase( $query );
    }



function ns_editNote() {

    $password = ns_checkPassword( "edit_note" );
    
    $uid = "";
    if( isset( $_REQUEST[ "uid" ] ) ) {
        $uid = $_REQUEST[ "uid" ];
        }
    else {
        echo "REJECTED";
        die();
        }
    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    
    $query = "SELECT body_text FROM $tableNamePrefix"."notes ".
        "WHERE uid = '$uid'";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );



    
    if( $numRows != 1 ) {
        echo "REJECTED";
        return;
        }

    
    $body_text = mysql_result( $result, 0, "body_text" );


    global $header, $footer;
    
    eval( $header );

    ns_menuBar( $password, "" );
    
    ?>
     <center>
        <FORM ACTION="server.php" METHOD="post">
     <INPUT TYPE="hidden" NAME="action"
          VALUE="update_note">
     <INPUT TYPE="hidden" NAME="uid"
          VALUE="<?php echo $uid; ?>">
     <INPUT TYPE="hidden" NAME="password"
          VALUE="<?php echo $password; ?>">          
     <INPUT TYPE="hidden" NAME="from_web" VALUE="1">

     <TEXTAREA NAME="body_text" COLS=90
          ROWS=20><?php echo htmlspecialchars( $body_text ); ?></TEXTAREA>
     <br>
     <INPUT TYPE="Submit" VALUE="Update">
      </center>
<?php
    
    eval( $footer );
    }




function ns_newNote() {

    $password = ns_checkPassword( "new_note" );    
    
    global $tableNamePrefix;

    
    /*
            "CREATE TABLE $tableName(" .
            "uid INT NOT NULL PRIMARY KEY AUTO_INCREMENT," .
            "hash CHAR(32) NOT NULL," .
            "creation_date DATETIME NOT NULL," .
            "change_date DATETIME NOT NULL," .
            "view_date DATETIME NOT NULL," .
            "title_line VARCHAR(60) NOT NULL," .
            "body_text LONGTEXT )";
    */

    global $header, $footer;
    
    eval( $header );

    ns_menuBar( $password, "" );

    ?>
        <center>
     <FORM ACTION="server.php" METHOD="post">
     <INPUT TYPE="hidden" NAME="action"
          VALUE="add_note">
     <INPUT TYPE="hidden" NAME="password"
          VALUE="<?php echo $password; ?>">          
     <INPUT TYPE="hidden" NAME="from_web" VALUE="1">

     <TEXTAREA NAME="body_text" COLS=90 ROWS=20></TEXTAREA>
     <br>
     <INPUT TYPE="Submit" VALUE="Create">
     </center>        
<?php
    
    eval( $footer );
    }








// general-purpose functions down here, many copied from seedBlogs

/**
 * Connects to the database according to the database variables.
 */  
function ns_connectToDatabase() {
    global $databaseServer,
        $databaseUsername, $databasePassword, $databaseName;
    
    
    mysql_connect( $databaseServer, $databaseUsername, $databasePassword )
        or ns_fatalError( "Could not connect to database server: " .
                       mysql_error() );
    
	mysql_select_db( $databaseName )
        or ns_fatalError( "Could not select $databaseName database: " .
                       mysql_error() );
    }


 
/**
 * Closes the database connection.
 */
function ns_closeDatabase() {
    mysql_close();
    }



/**
 * Queries the database, and dies with an error message on failure.
 *
 * @param $inQueryString the SQL query string.
 *
 * @return a result handle that can be passed to other mysql functions.
 */
function ns_queryDatabase( $inQueryString ) {

    $result = mysql_query( $inQueryString )
        or ns_fatalError( "Database query failed:<BR>$inQueryString<BR><BR>" .
                       mysql_error() );

    return $result;
    }



/**
 * Checks whether a table exists in the currently-connected database.
 *
 * @param $inTableName the name of the table to look for.
 *
 * @return 1 if the table exists, or 0 if not.
 */
function ns_doesTableExist( $inTableName ) {
    // check if our table exists
    $tableExists = 0;
    
    $query = "SHOW TABLES";
    $result = ns_queryDatabase( $query );

    $numRows = mysql_numrows( $result );


    for( $i=0; $i<$numRows && ! $tableExists; $i++ ) {

        $tableName = mysql_result( $result, $i, 0 );
        
        if( $tableName == $inTableName ) {
            $tableExists = 1;
            }
        }
    return $tableExists;
    }



function ns_log( $message ) {
    global $enableLog, $tableNamePrefix;

    $slashedMessage = addslashes( $message );
    
    if( $enableLog ) {
        $query = "INSERT INTO $tableNamePrefix"."log VALUES ( " .
            "'$slashedMessage', CURRENT_TIMESTAMP );";
        $result = ns_queryDatabase( $query );
        }
    }



/**
 * Displays the error page and dies.
 *
 * @param $message the error message to display on the error page.
 */
function ns_fatalError( $message ) {
    //global $errorMessage;

    // set the variable that is displayed inside error.php
    //$errorMessage = $message;
    
    //include_once( "error.php" );

    // for now, just print error message
    $logMessage = "Fatal error:  $message";
    
    echo( $logMessage );

    ns_log( $logMessage );
    
    die();
    }



/**
 * Displays the operation error message and dies.
 *
 * @param $message the error message to display.
 */
function ns_operationError( $message ) {
    
    // for now, just print error message
    echo( "ERROR:  $message" );
    die();
    }


/**
 * Recursively applies the addslashes function to arrays of arrays.
 * This effectively forces magic_quote escaping behavior, eliminating
 * a slew of possible database security issues. 
 *
 * @inValue the value or array to addslashes to.
 *
 * @return the value or array with slashes added.
 */
function ns_addslashes_deep( $inValue ) {
    return
        ( is_array( $inValue )
          ? array_map( 'ns_addslashes_deep', $inValue )
          : addslashes( $inValue ) );
    }



/**
 * Recursively applies the stripslashes function to arrays of arrays.
 * This effectively disables magic_quote escaping behavior. 
 *
 * @inValue the value or array to stripslashes from.
 *
 * @return the value or array with slashes removed.
 */
function ns_stripslashes_deep( $inValue ) {
    return
        ( is_array( $inValue )
          ? array_map( 'sb_stripslashes_deep', $inValue )
          : stripslashes( $inValue ) );
    }



function ns_checkPassword( $inFunctionName ) {
    $password = "";
    if( isset( $_REQUEST[ "password" ] ) ) {
        $password = $_REQUEST[ "password" ];
        }

    global $accessPasswords;
    
    if( ! in_array( $password, $accessPasswords ) ) {
        echo "REJECTED";

        ns_log( "Failed $inFunctionName access with password:  $password" );

        die();
        }

    return $password;
    }

?>
