#!/bin/sh
set -e

# Volume Railway só está montado após o container iniciar (não no pre-deploy).
/usr/local/bin/storage-bootstrap.sh
exec /usr/local/bin/migrate-db.sh
