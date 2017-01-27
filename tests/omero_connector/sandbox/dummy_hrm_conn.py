from omero.gateway import BlitzGateway

user='demo01'
passwd='Dem0o1'
# host='localhost'
host='vbox.omero'
port=4064

conn = BlitzGateway(user, passwd, host=host, port=port)
conn.connect()
