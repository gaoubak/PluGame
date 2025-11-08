#!/bin/bash
set -e

echo "================================"
echo "  Code Quality Checks"
echo "================================"
echo ""

echo "🔍 Running PHP_CodeSniffer..."
vendor/bin/phpcs --colors

echo ""
echo "🔬 Running PHPStan (Static Analysis)..."
vendor/bin/phpstan analyse --no-progress

echo ""
echo "✅ All linting checks passed!"
