<?php

// Basic settings
// You must set these for the server to work

$databaseServer = "localhost";
$databaseUsername = "testUser";
$databasePassword = "testPassword";
$databaseName = "test";

// The URL of to the server.php script.
$fullServerURL = "http://localhost/jcr13/noteServer/server.php";

// End Basic settings



// Customization settings

// Adjust these to change the way the server  works.


// Prefix to use in table names (in case more than one application is using
// the same database).  Two tables are created:  "games" and "columns".
//
// If $tableNamePrefix is "test_" then the tables will be named
// "test_games" and "test_columns".  Thus, more than one server
// installation can use the same database (or the server can share a database
// with another application that uses similar table names).
$tableNamePrefix = "noteServer_";


$enableLog = 1;

// for web-based admin access
$accessPasswords = array( "secret", "letmein" );


// for list views
$notesPerPage = 2;



// header and footers for various pages
$header = "include( \"header.php\" );";
$footer = "include( \"footer.php\" );";




?>