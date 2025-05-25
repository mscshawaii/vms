import os

# Set your project directory
project_dir = "C:/laragon/www/vessel_management_system"

# What to search for
search_term = "cid"

# Collect matches
matches = []

# Scan files
for root, dirs, files in os.walk(project_dir):
    for file in files:
        if file.endswith(".php"):
            full_path = os.path.join(root, file)
            with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
                for i, line in enumerate(f, 1):
                    if search_term in line and 'company_id' not in line:  # skip updated ones
                        matches.append((full_path, i, line.strip()))

# Display results
for file, line_num, line_text in matches:
    print(f"{file} (Line {line_num}): {line_text}")
