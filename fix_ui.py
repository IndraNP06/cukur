
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    # Replacements for paddings and gaps
    replacements = {
        r"\bp-8\b": "p-5 lg:p-6",
        r"\bp-6\b": "p-4 lg:p-5",
        r"\bgap-8\b": "gap-5 lg:gap-6",
        r"\bgap-6\b": "gap-4 lg:gap-5",
        r"\bmb-16\b": "mb-10 lg:mb-12",
        r"\bmb-12\b": "mb-8 lg:mb-10",
        r"\bpy-8\b": "py-5 lg:py-6",
        r"\bpy-6\b": "py-4 lg:py-5",
        r"\bpx-8\b": "px-5 lg:px-6",
        r"\bpx-6\b": "px-4 lg:px-5",
        r"\btext-headline-xl\b": "text-headline-lg",
        r"\btext-headline-lg\b": "text-headline-md",
    }

    new_content = content
    for pattern, replacement in replacements.items():
        new_content = re.sub(pattern, replacement, new_content)

    if new_content != content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        try:
            print(f"Updated {filepath}")
        except:
            pass

def main():
    target_dirs = [
        "resources/views/livewire",
        "resources/views/components"
    ]
    
    for d in target_dirs:
        for root, dirs, files in os.walk(d):
            for file in files:
                if file.endswith(".blade.php"):
                    process_file(os.path.join(root, file))

if __name__ == "__main__":
    main()

