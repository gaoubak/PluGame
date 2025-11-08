#!/bin/bash
set -e

echo "================================"
echo "  Running Test Suite"
echo "================================"
echo ""

echo "🧪 Running PHPUnit tests..."
vendor/bin/phpunit --colors=always

echo ""
echo "✅ All tests passed!"
