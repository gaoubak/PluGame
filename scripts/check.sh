#!/bin/bash
set -e

echo "================================"
echo "  Full Quality Check"
echo "================================"
echo ""

# Run linting
./scripts/lint.sh

echo ""

# Run tests
./scripts/test.sh

echo ""
echo "================================"
echo "🎉 All checks passed! Ready to commit."
echo "================================"
