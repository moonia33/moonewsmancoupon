#!/usr/bin/env bash
set -euo pipefail
MODULE=moonewsmancoupon
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
MODULE_DIR=$(cd "$SCRIPT_DIR/.." && pwd)

mkdir -p "$MODULE_DIR/dist"
TMP_BUILD="$MODULE_DIR/.build"
rm -rf "$TMP_BUILD" && mkdir -p "$TMP_BUILD/$MODULE"
find "$MODULE_DIR" -maxdepth 1 -mindepth 1 \
  ! -name '.git' \
  ! -name 'dist' \
  ! -name '.build' \
  -exec cp -r {} "$TMP_BUILD/$MODULE/" \;
(cd "$TMP_BUILD" && zip -r "$MODULE_DIR/dist/${MODULE}.zip" "$MODULE")
rm -rf "$TMP_BUILD"
echo "Built: $MODULE_DIR/dist/${MODULE}.zip"