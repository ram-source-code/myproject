cacert.pem  — Mozilla CA certificate bundle from https://curl.se/ca/cacert.pem (MPL 2.0), refresh periodically, used for outgoing push requests ONLY when PHP has no
              CA bundle configured (curl.cainfo / openssl.cafile empty), which is typical on Windows/macOS XAMPP.
openssl.cnf — minimal OpenSSL config so key generation works on Windows PHP builds.
Neither file is web-accessible (app/ is blocked by .htaccess).
