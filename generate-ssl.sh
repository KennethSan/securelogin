#!/bin/bash

# Create SSL directory if it doesn't exist
mkdir -p nginx/ssl

# Generate private key
openssl genrsa -out nginx/ssl/localhost.key 2048

# Generate certificate signing request
openssl req -new -key nginx/ssl/localhost.key -out nginx/ssl/localhost.csr -subj "/C=US/ST=Dev/L=Dev/O=Dev/CN=localhost"

# Generate self-signed certificate
openssl x509 -req -days 365 -in nginx/ssl/localhost.csr -signkey nginx/ssl/localhost.key -out nginx/ssl/localhost.crt

# Set proper permissions
chmod 600 nginx/ssl/localhost.key
chmod 644 nginx/ssl/localhost.crt

# Clean up CSR file
rm nginx/ssl/localhost.csr

echo "SSL certificates generated successfully!"
echo "Certificate: nginx/ssl/localhost.crt"
echo "Private key: nginx/ssl/localhost.key"