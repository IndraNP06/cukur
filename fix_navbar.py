
import os
import re

filepath = "resources/views/components/navbar.blade.php"
with open(filepath, "r", encoding="utf-8") as f:
    content = f.read()

# Replace the specific occurrences
new_content = re.sub(
    r"transition-all w-12 lg:w-12",
    "transition-all w-64 lg:w-96",
    content
)

new_content = re.sub(
    r"right-0 mt-2 w-12 bg-surface",
    "right-0 mt-2 w-80 bg-surface",
    new_content, count=1 # Notification is the first one
)

new_content = re.sub(
    r"right-0 mt-2 w-12 bg-surface",
    "right-0 mt-2 w-56 bg-surface",
    new_content
) # The remaining are user profile dropdowns

with open(filepath, "w", encoding="utf-8") as f:
    f.write(new_content)
print("Fixed navbar dropdown widths")

