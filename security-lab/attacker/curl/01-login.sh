#!/bin/bash
# Login script - gets auth token for a user
# Usage: ./01-login.sh alice@lab.test

source "$(dirname "$0")/00-vars.sh"

EMAIL="${1:-$ALICE_EMAIL}"

echo "Logging in as: $EMAIL"

RESPONSE=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")

echo "Response:"
echo "$RESPONSE" | jq .

# Extract and export the token for use in other scripts
TOKEN=$(echo "$RESPONSE" | jq -r '.token // empty')

if [ -n "$TOKEN" ]; then
    echo ""
    echo "Token obtained! Export with:"
    echo "  export TOKEN=$TOKEN"
    export TOKEN="$TOKEN"
else
    echo "Failed to obtain token. Check credentials."
    exit 1
fi
