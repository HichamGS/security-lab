#!/bin/bash
# IDOR Probe - Test accessing other users' notes
# Usage: First run 01-login.sh to get TOKEN, then run this script

source "$(dirname "$0")/00-vars.sh"

if [ -z "$TOKEN" ]; then
    echo "Error: TOKEN not set. Run ./01-login.sh first or export TOKEN manually."
    exit 1
fi

echo "=== IDOR Probe: Testing Note Access ==="
echo ""

# Try to access note ID 1 (likely belongs to Alice)
echo "Attempting to access note ID 1..."
curl -s -X GET "$API_URL/api/notes/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

echo ""

# Try to access note ID 4 (likely belongs to Bob)
echo "Attempting to access note ID 4 (Bob's note)..."
curl -s -X GET "$API_URL/api/notes/4" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

echo ""
echo "If you see 403 Forbidden, the IDOR protection is working!"
echo "If you see note content, there may be an authorization issue."
