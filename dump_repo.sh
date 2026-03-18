#!/bin/bash

# File di output
OUTPUT_FILE="repo_dump.txt"

# Directory base (default: corrente)
BASE_DIR="${1:-.}"

# Pattern da escludere
EXCLUDE_DIRS=(
  ".git"
  "node_modules"
  "vendor"
  "dist"
  "build"
  ".next"
  ".cache"
)

EXCLUDE_FILES=(
  "*.lock"
  "*.log"
  "*.zip"
  "*.tar"
  "*.gz"
  "*.jpg"
  "*.jpeg"
  "*.png"
  "*.gif"
  "*.webp"
  "*.ico"
  "*.pdf"
  "*.mp4"
  "*.mp3"
  "*.woff"
  "*.woff2"
  "*.LICENSE*"
  "istruzioni.txt"
  "ARCHITECTURE.md"
  "DEVELOPMENT_PLAN.md"
  "*.gitignore*"
)

# Costruzione dinamica dei parametri find
EXCLUDE_ARGS=()

for dir in "${EXCLUDE_DIRS[@]}"; do
  EXCLUDE_ARGS+=(-path "*/$dir/*" -prune -o)
done

for file in "${EXCLUDE_FILES[@]}"; do
  EXCLUDE_ARGS+=(-name "$file" -prune -o)
done

# Reset file output
> "$OUTPUT_FILE"

echo "📦 Generazione dump repository..."
echo "Directory base: $BASE_DIR"
echo "Output: $OUTPUT_FILE"
echo ""

# Ciclo sui file
find "$BASE_DIR" "${EXCLUDE_ARGS[@]}" -type f -print | sort | while read -r file; do

  # Controllo file testuale
  if file "$file" | grep -q "text"; then

    echo "==================================================" >> "$OUTPUT_FILE"
    echo "FILE: $file" >> "$OUTPUT_FILE"
    echo "==================================================" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"

    cat "$file" >> "$OUTPUT_FILE"

    echo -e "\n\n" >> "$OUTPUT_FILE"
  fi

done

echo "✅ Dump completato in $OUTPUT_FILE"
