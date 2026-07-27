#!/bin/bash
# Environment variables for security lab testing
# Source this file before running other scripts: source 00-vars.sh

export API_URL="http://localhost:8080"
export ALICE_EMAIL="alice@lab.test"
export BOB_EMAIL="bob@lab.test"
export ADMIN_EMAIL="admin@lab.test"
export PASSWORD="password"

echo "Security Lab Variables Loaded:"
echo "  API URL: $API_URL"
echo "  Test Users: alice@lab.test, bob@lab.test, admin@lab.test"
echo "  Password: $PASSWORD"
