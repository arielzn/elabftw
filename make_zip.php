<?php
/********************************************************************************
*                                                                               *
*   Copyright 2012 Nicolas CARPi (nicolas.carpi@gmail.com)                      *
*   http://www.elabftw.net/                                                     *
*                                                                               *
********************************************************************************/

/********************************************************************************
*  This file is part of eLabFTW.                                                *
*                                                                               *
*    eLabFTW is free software: you can redistribute it and/or modify            *
*    it under the terms of the GNU Affero General Public License as             *
*    published by the Free Software Foundation, either version 3 of             *
*    the License, or (at your option) any later version.                        *
*                                                                               *
*    eLabFTW is distributed in the hope that it will be useful,                 *
*    but WITHOUT ANY WARRANTY; without even the implied                         *
*    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR                    *
*    PURPOSE.  See the GNU Affero General Public License for more details.      *
*                                                                               *
*    You should have received a copy of the GNU Affero General Public           *
*    License along with eLabFTW.  If not, see <http://www.gnu.org/licenses/>.   *
*                                                                               *
********************************************************************************/
require_once('inc/common.php');
require_once('inc/head.php');
$page_title='Make zip';
require_once('inc/menu.php');
require_once('inc/info_box.php');
// Test if there is zip
if(!class_exists('ZipArchive')) {
    die("<p>You are missing the ZipArchive class in php. Uncomment the line extension=zip.so in /etc/php/php.ini.</p>");
}

// Switch exp/items just for the table to search in sql requests
if ($_GET['type'] === 'exp') {
    $table = 'experiments';
} elseif ($_GET['type'] === 'items') {
    $table = 'items';
} else {
    die('bad type');
}
// Check id is valid and assign it to $id
if(isset($_GET['id']) && !empty($_GET['id'])) {
    $id_arr = explode(" ", $_GET['id']);
    // BEGIN ZIP
    // name of the downloadable file
    $zipname = kdate().".export.elabftw";
    // name of the real file on the system, in /tmp to avoid clugging up uploads/
    $zipfile = "/tmp/".$zipname."-".hash("sha512", uniqid(rand(), true)).".zip";

    $zip = new ZipArchive;
    $res = $zip->open($zipfile, ZipArchive::CREATE);
    if ($res === true) {
        foreach($id_arr as $id) {
            // MAIN LOOP
            ////////////////

            // SQL to get title, body and date
            $sql = "SELECT title, body, date, userid FROM ".$table." WHERE id = $id";
            $req = $bdd->prepare($sql);
            $req->execute();
            $data = $req->fetch();
                $title = stripslashes($data['title']);
                // make a title without special char for folder inside .zip
                $clean_title = preg_replace('/[^A-Za-z0-9]/', '_', $title);
                $date = $data['date'];
                // name of the folder
                $folder = $date."-".$clean_title;
                $body = stripslashes($data['body']);
            $req->closeCursor();

            // SQL to get firstname + lastname
            $sql = "SELECT firstname,lastname FROM users WHERE userid = ".$_SESSION['userid'];
            $req = $bdd->prepare($sql);
            $req->execute();
            $data = $req->fetch();
                $firstname = $data['firstname'];
                $lastname = $data['lastname'];
            // SQL to get tags
            $sql = "SELECT tag FROM ".$table."_tags WHERE item_id = $id";
            $req = $bdd->prepare($sql);
            $req->execute();
            $tags = null;
            while($data = $req->fetch()){
                $tags .= stripslashes($data['tag']).' ';
            }


            // SQL to get filesattached
            $sql = "SELECT real_name, long_name, comment FROM uploads WHERE item_id = ".$id;
            $req = $bdd->prepare($sql);
            $req->execute();
            $real_name = array();
            $long_name = array();
            $comment = array();
            while ($data = $req->fetch()) {
                $real_name[] = $data['real_name'];
                $long_name[] = $data['long_name'];
                $comment[] = $data['comment'];
            }


                // create an html page
                $html = "<!DOCTYPE html><html><head><meta http-equiv='Content-Type' content='text/html'; charset='utf-8'><title>";
                $html .= $title;
                $html .= "</title></head><body>";
                $html .= "Date : ".$date."<br />
            <span style='text-align: right;'>By : ".$firstname." ".$lastname."</span><br />
            <div style='text-align: center;'><font size='10'>".$title."</font></span></div><br /><br />
            ".$body."<br />";
                // files attached ?
                $filenb = count($real_name);
                if ($filenb > 0){
                    if ($filenb == 1){
                        $html .= '~~~~<br />
            Attached file :<br />
            ';
                    } else {
                        $html .= '~~~~<br />
            Attached files :<br />
            ';
                    }
                    for ($i=0;$i<$filenb;$i++){
                        $html .= "<a href='".$real_name[$i]."'>".$real_name[$i]."</a> (".stripslashes(str_replace("&#39;", "'", utf8_decode($comment[$i]))).").<br />";
                        // add files to archive
                        $zip->addFile($ini_arr['upload_dir'].$long_name[$i], $folder."/".$real_name[$i]);
                    }

                }
                // FOOTER
                $html .= "~~~~<br />
                File created with <strong>elabFTW</strong> -- Free open source lab manager<br />
                <a href='http://www.elabftw.net'>eLabFTW.net</a>";
                $html .= "</body></html>";
                // CREATE TXT FILE
                // utf8 ftw
                $html = utf8_encode($html);
                // add header for utf-8
                $html = "\xEF\xBB\xBF".$html;
                $txtfile = '/tmp/elabftw-'.uniqid();
                $tf = fopen($txtfile, 'w+');
                fwrite($tf, $html);
                fclose($tf);
                // add html file
                $zip->addFile($txtfile, $folder."/".$clean_title.".html");
                // add a PDF, too
                $pdfname = make_pdf($id, $table, '/tmp');
                $zip->addFile('/tmp/'.$pdfname, $folder."/".$pdfname);
                // delete files
                //unlink($txtfile);
                //unlink('/tmp/'.$pdfname);

        } // end foreach
            $zip->close();

// PAGE BEGIN
echo "<div class='item'>";
// Get zip size
$zipsize = filesize($zipfile);
// Display download link (with attribute type=zip for download.php)
echo "<p>Download archive <span class='filesize'>(".format_bytes($zipsize).")</span> :<br />
    <img src='themes/".$_SESSION['prefs']['theme']."/img/download.png' alt='' /> 
    <a href='download.php?f=".$zipfile."&name=".$zipname.".zip&type=zip' target='_blank'>".$zipname.".zip</a></p>";
// Check if zip is < 10 Mo, display send by email dialog
if ($zipsize < 10485760) {
    // SQL to get all users and emails
    $sql = "SELECT firstname, lastname, email, userid FROM users";
    $req = $bdd->prepare($sql);
    $req->execute();
    echo "<p>Send zip archive to :
        <form style='margin-top:-15px' method='post' action='send_zip.php'><img src='themes/".$_SESSION['prefs']['theme']."/img/mail.gif' alt='mail' /> <select name='userid'>";
    while($data = $req->fetch()){
         echo "<option value='".$data['userid']."'>".$data['firstname']." ".$data['lastname']."</option>";
    }
    echo "</select> <input type=submit value='send' />
        <input type='hidden' name='zipfile' value='".$zipfile."'>
        </form></p></div>";
} else {
    echo "<p>Zip archive is too big to be sent by email.</p>";
}
} else {
    echo 'Archive creation failed :(';
}

require_once('inc/footer.php');
} else {
    die("The id parameter in the URL isn't a valid experiment ID");
}
