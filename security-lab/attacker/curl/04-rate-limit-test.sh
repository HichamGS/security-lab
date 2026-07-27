#!/bin/bash
# Rate Limit Test - Repeated login attempts to check for rate limiting
# Usage: ./04-rate-limit-test.sh

source "$(dirname "$0")/00-vars.sh"

echo "=== Rate Limiting Test: Repeated Login Attempts ==="
echo ""
echo "Sending 20 failed login attempts to check for rate limiting..."
echo ""

for i in {1..20}; do
  echo -n "Attempt $i: "
  
  RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/api/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"alice@lab.test\",\"password\":\"wrongpassword\"}")
  
  HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
  BODY=$(echo "$RESPONSE" | head -n-1 | jq -r '.message // .error // "Unknown"')
  
  echo "HTTP $HTTP_CODE - $BODY"
  
  if [[ "$HTTP_CODE" == "429" ]]; then
    echo ""
    echo ">>> Rate limiting kicked in at attempt $i! <<<"
    break
  fi
done

echo ""
if [[ "$HTTP_CODE" != "429" ]]; then
  echo "WARNING: No rate limiting detected after 20 attempts."
  echo "Consider adding rate limiting middleware to auth routes."
fi
