#!/bin/bash

# Test script to generate mutation badge locally

# Calculate MSI (Mutation Score Indicator) from infection summary
if [ -f "build/infection/summary.txt" ]; then
  TOTAL=$(grep "^Total:" build/infection/summary.txt | grep -o "[0-9]*" || echo "0")
  ESCAPED=$(grep "^Escaped:" build/infection/summary.txt | grep -o "[0-9]*" || echo "0")
  TIMED_OUT=$(grep "^Timed Out:" build/infection/summary.txt | grep -o "[0-9]*" || echo "0")
  SKIPPED=$(grep "^Skipped:" build/infection/summary.txt | grep -o "[0-9]*" || echo "0")

  echo "Total: $TOTAL"
  echo "Escaped: $ESCAPED"
  echo "Timed Out: $TIMED_OUT"
  echo "Skipped: $SKIPPED"

  if [ "$TOTAL" -gt 0 ]; then
    KILLED=$((TOTAL - ESCAPED - TIMED_OUT - SKIPPED))
    MSI=$(awk "BEGIN {printf \"%.2f\", ($KILLED / $TOTAL) * 100}")
  else
    MSI="0.00"
  fi
else
  MSI="0.00"
fi

echo "Calculated MSI: $MSI"

# Determine color based on MSI score
if awk "BEGIN {exit !($MSI >= 80)}"; then
  COLOR="brightgreen"
elif awk "BEGIN {exit !($MSI >= 60)}"; then
  COLOR="yellow"
else
  COLOR="red"
fi

echo "Color: $COLOR"

# Create badge JSON for shields.io
echo "{\"schemaVersion\":1,\"label\":\"Mutation Score\",\"message\":\"$MSI%\",\"color\":\"$COLOR\"}" > mutation-badge.json

echo "Badge created:"
cat mutation-badge.json
