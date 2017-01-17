<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

// The admin must be logged on
if ((!isset($_SESSION['user'])) ||
    (!$_SESSION['user']->isAdmin())
) {
    header("Location: " . "login.php");
    exit();
}

$message = "";

$db = new DatabaseConnection();

if (!isset($_GET["add"]["name"]) || empty($_GET["add"]["name"])) {
    $message .= "One or more fields empty: no servers to add.\n";
} elseif (!isset($_GET["add"]["path"]) || empty($_GET["add"]["path"])) {
    $message .= "One or more fields empty: no servers to add.\n";
} elseif (!isset($_GET["add"]["gpuId"]) || empty($_GET["add"]["gpuId"])) {
    $message .= "One or more fields empty: no servers to add.\n";
} else {
    $serverName = $_GET["add"]["name"];
    $huPath     = $_GET["add"]["path"];
    $gpuId      = $_GET["add"]["gpuId"];
    if ($db->addServer($serverName, $huPath, $gpuId)) {
        $message .= "Server could not be added.\n";
    } else {
        $message .= "Server '$serverName' added successfully.\n";
    }
}

if (isset($_GET["remove"]) && !empty($_GET["remove"])) {
    foreach ($_GET["remove"] as $serverName => $action) {
        if ($db->removeServer($serverName)) {
            $message .= "Server '$serverName' could not be removed.\n";
        } else {
            $message .= "Server '$serverName' successfully removed.\n";
        }
    }
}


include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensGPU'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">
    <h3>
          <img alt="Servers&GPUs" src="./images/gpu.png" width="40"/>
                Servers & GPUs
    </h3>

                
    <form method="GET" action="" id="GPU">
       <table id="Servers">
          <tr>
              <th>Server Name</th>
              <th>Hucore Path</th>
              <th>GPU ID</th>
              <th>Action</th>
          </tr>

          <?php
                /* First, show the existing servers with a 'Remove' button. */
                $servers = $db->getAllServers();
                foreach ($servers as $server) {
                   foreach ($server as $key => $value) {
                      if (strpos($key, 'name') !== false) {
                          $serverName = $value;
                          $name = split(" ", $serverName);
                          $name = $name[0];
                      }
                      if (strpos($key, 'huscript_path') !== false) {
                          $path = $value;
                      }
                      if (strpos($key, 'gpuId') !== false) {
                          $gpuId = $value;
                      }
                   }
                 ?>

                 <tr>
                 <td><?php echo $name;?> </td>
                 <td><?php echo $path;?> </td>
                 <td><?php echo $gpuId;?></td>
                 <td><input type="submit"
                       name="remove[<?php echo $serverName;?>]"
                       value="Remove"
                       onclick="document.forms[\'GPU\'].submit()"/>
                 </td> 
                 </tr>
          <?php 
          }
          ?>

          <td><input name="add[name]" type="text" size="6"></td>
          <td><input name="add[path]" type="text" size="6"></td>
          <td><input name="add[gpuId]" type="text" size="2"></td>
          <td><input type="submit" value="Add"
               onclick="document.forms['GPU'].submit()"/></td>

       </table>
    </form>

                    
<br /><br />
    <fieldset>
        <legend>log</legend>
            <textarea title="Log" rows="10" readonly="readonly">
<?php

echo $message;

?>
            </textarea>
    </fieldset>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>
            This page allows you to manage processing machines and GPU cards.
            Please visit <a href="https://svi.nl/HuygensGPU">Huygens GPU</a>
            for detailed instructions on how to install CUDA.
        </p>

        <p>
            The Huygens deconvolution logs can be reached via the
            user account: <b>'Results' -> 'Select an image' ->
                'Detailed view' -> 'log'.</b>
            The log shows whether the image has been processed on the CPU
            or on the GPU.
        </p>

        <p>
            GPU deconvolution is available in Huygens from version
            <b>15.10</b> onwards. Multi GPU support can be found in Huygens
            <b>16.10.1</b> or higher.
        <br>

    </div>

    <div id="message">
    </div>

</div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>
