#!/bin/bash

echo "================================"
echo "  Auto-Fix Code Style Issues"
echo "================================"
echo ""

echo "🔧 Running PHP_CodeSniffer auto-fix..."
vendor/bin/phpcbf --colors

echo ""
echo "✅ Auto-fix complete! Check the changes with git diff."
