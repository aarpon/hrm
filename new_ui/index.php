<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Huygens Remote Manager</title>
    <link rel=icon href="../images/hrm.ico" type="image/vnd.microsoft.icon">
    
    <link href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/hrm.css" rel="stylesheet">
    

  </head>

  <body role="document">
    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="#">Huygens Remote Manager</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
          <ul class="nav navbar-nav">
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Help <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <li><a href="http://huygens-remote-manager.readthedocs.io/en/latest/user/index.html">Manual</a></li>
                <li><a href="http://hrm.svi.nl:8080/redmine/projects/public/issues/new">Bug report</a></li>
                <li><a href="https://svi.nl/HuygensRemoteManager">About</a></li>
              </ul>
            </li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>
    
    <div class="container-fluid main">
      <h1 class="page-header">Welcome
      </h1>
      <div class="row">
        <div class="col-md-9">
          <p>
            The Huygens Remote Manager is an easy to use interface to the Huygens Software by Scientific Volume Imaging B.V.  that allows for multi-user, large-scale deconvolution and analysis.
          </p>
          
          <h2>Collaborators</h2>
          <p>&nbsp;</p>
          <p>
            <div class="row">
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_epfl.png">
                </p>
                <p class="text-center small">
                  EPF Lausanne<br />
                  Biological and Optics Platform
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_fmi.png">
                </p>
                <p class="text-center small">
                  Friedrich Miescher Institute<br />
                  Faculty for Advanced Imaging and Microscopy
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_mri.png">
                </p>
                <p class="text-center small">
                  Montpellier RIO Imaging
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_bsse.png">
                </p>
                <p class="text-center small">
                  ETH Zurich<br />
                  Single-Cell Unit
                </p>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_svi.png">
                </p>
                <p class="text-center small">
                  Scientific Volume Imaging
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_lin_en.png">
                </p>
                <p class="text-center small">
                  Leibniz Institute for Neurobiology Magedeburg
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_bio_basel.png">
                </p>
                <p class="text-center small">
                  Biozentrum Basel<br/>
                  University of Basel<br>
                  The Center for Molecular Sciences
                </p>
              </div>
              <div class="col-md-3 text-center">
                <p>
                  <img class="logo" src="../images/logo_cni_pp.png">
                </p>
                <p class="text-center small">
                  Comninatorial Neuroimaging Magedeburg
                </p>
              </div>
            </div>
          </p>
        </div>
        
        <div class="col-md-3">
          <div class="panel panel-default">
            <div class="panel-body">
              <h2>Login</h2>
              <form>
                <div class="form-group">
                  <label for="inputUsername">Username</label>
                  <input type="text" class="form-control" id="inputUsername" placeholder="Username">
                </div>
                <div class="form-group">
                  <label for="inputPassword">Password</label>
                  <input type="password" class="form-control" id="inputPassword" placeholder="Password">
                </div>
                <button type="submit" class="btn btn-default">Login</button>
              </form>
              <p>&nbsp;</p>
              <p>
                No HRM account yet?<br/>
                Register <a href="../registration.php">here</a>.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="container">
      <p class="text-center small">
        created 2004 by Volker Bäcker and released under the terms of the CeCILL license</br>
        extended 2006-2016 by Asheesh Gulati, Alessandra Griffa, José Viña, Daniel Sevilla, Niko Ehrenfeuchter, Torsten Stöter, Olivier Burri and Aaron Ponti
      </p>
    </div>
    
    <script src="../vendor/components/jquery/jquery.min.js"></script>
    <script src="../vendor/twbs/bootstrap/dist/js/bootstrap.min.js"></script>
    

  </body>
</html>