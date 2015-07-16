import gc3libs
gc3libs.configure_logger(30)
gc3conffile = 'config/samples/gc3pie_localhost.conf'
engine = gc3libs.create_engine(gc3conffile)
core = engine._core
