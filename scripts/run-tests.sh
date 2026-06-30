#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Test Runner — Wraps PHPUnit with convenience options
# ============================================================

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

SUITE=""
COVERAGE=false
FILTER=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            SUITE="--testsuite 'Unit Tests'"
            shift
            ;;
        --integration)
            SUITE="--testsuite 'Integration Tests'"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --filter=*)
            FILTER="--filter=${1#*=}"
            shift
            ;;
        --filter)
            FILTER="--filter=$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo
            echo "Options:"
            echo "  --unit          Run unit tests only"
            echo "  --integration   Run integration tests only"
            echo "  --coverage      Generate HTML coverage report"
            echo "  --filter=NAME   Run only tests matching NAME"
            echo "  -h, --help      Show this help"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Build command
CMD="vendor/bin/phpunit -c phpunit.xml.dist --colors=always"

if [ -n "$SUITE" ]; then
    CMD="$CMD $SUITE"
fi

if [ -n "$FILTER" ]; then
    CMD="$CMD $FILTER"
fi

if [ "$COVERAGE" = true ]; then
    CMD="$CMD --coverage-html coverage/ --coverage-text"
fi

echo -e "${GREEN}Running: $CMD${NC}"
echo
eval "$CMD"

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "\n${GREEN}✅ All tests passed!${NC}"
    if [ "$COVERAGE" = true ]; then
        echo -e "${GREEN}📊 Coverage report: coverage/index.html${NC}"
    fi
else
    echo -e "\n${RED}❌ Some tests failed.${NC}"
fi

exit $EXIT_CODE
