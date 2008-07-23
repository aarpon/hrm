<?php

// php page: login.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_GET["action"])) {
    if ($_GET["action"] == "dbupdate")
        include("dbupdate.php");
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited" onclick="clean()">exit</a></li>
            <li><a href="user_management.php">users</a></li>
            <li><a href="select_parameter_settings.php" onclick="clean()">parameters</a></li>
            <li><a href="select_task_settings.php" onclick="clean()">tasks</a></li>
            <li><a href="account.php">account</a></li>
            <li><a href="job_queue.php" onclick="clean()">queue</a></li>
            <li>update</li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpUserManagement')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Update</h3>
        
        <fieldset>
            <legend>changelog</legend>
            <textarea rows="15" readonly="readonly">
<?php

include("changelog");

?>
            </textarea>
        </fieldset>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <p>
                This page allows you to check and update the database using a
                patch script release downloaded from the project website
                (<a href="http://sourceforge.net/projects/hrm">http://sourceforge.net/projects/hrm</a>).
            </p>
            
            <form method="GET" action="" id="dbupdate">
                <input type="hidden" name="action" value="dbupdate">
            </form>
            
            <input type="button" name="" value="database update" onclick="document.forms['dbupdate'].submit()" />
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>
