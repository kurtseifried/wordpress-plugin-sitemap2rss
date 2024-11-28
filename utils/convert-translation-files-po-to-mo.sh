#!/bin/bash

# Loop through all .po files in the current directory
for po_file in *.po; do
    # Extract the base name of the file (without extension)
    base_name="${po_file%.po}"

    # Define the output .mo file name
    mo_file="${base_name}.mo"

    # Compile the .po file into .mo using WP-CLI
    wp i18n make-mo "$po_file" "$mo_file"

    # Check if the command was successful
    if [[ $? -eq 0 ]]; then
        echo "Successfully compiled $po_file to $mo_file"
    else
        echo "Failed to compile $po_file" >&2
    fi
done
