
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    # Replacements for anomalies
    replacements = {
        r"lg:p-4 lg:p-5": "lg:p-5",
        r"lg:p-5 lg:p-6": "lg:p-6",
        r"lg:gap-4 lg:gap-5": "lg:gap-5",
        r"lg:gap-5 lg:gap-6": "lg:gap-6",
        r"lg:mb-8 lg:mb-10": "lg:mb-10",
        r"lg:mb-10 lg:mb-12": "lg:mb-12",
        r"lg:py-4 lg:py-5": "lg:py-5",
        r"lg:py-5 lg:py-6": "lg:py-6",
        r"lg:px-4 lg:px-5": "lg:px-5",
        r"lg:px-5 lg:px-6": "lg:px-6",
        r"md:p-4 lg:p-5": "md:p-5", # wait, `md:p-6` became `md:p-4 lg:p-5`. It should be `md:p-5`. Wait, originally `md:p-6` so `md:p-5` is correct.
        r"md:p-5 lg:p-6": "md:p-6", 
        r"md:px-4 lg:px-5": "md:px-5",
        r"md:px-5 lg:px-6": "md:px-6",
        r"md:py-4 lg:py-5": "md:py-5",
        r"md:py-5 lg:py-6": "md:py-6",
        r"md:gap-4 lg:gap-5": "md:gap-5",
        r"md:gap-5 lg:gap-6": "md:gap-6",
        r"md:mb-8 lg:mb-10": "md:mb-10",
        r"md:mb-10 lg:mb-12": "md:mb-12",
        r"sm:p-4 lg:p-5": "sm:p-5",
        r"sm:px-4 lg:px-5": "sm:px-5",
        r"sm:py-4 lg:py-5": "sm:py-5",
        r"p-4 lg:p-5 lg:p-5 lg:p-6": "p-4 lg:p-5", # "p-6 lg:p-8" -> "p-4 lg:p-5 lg:p-5 lg:p-6". We want "p-4 lg:p-5".
    }

    new_content = content
    for pattern, replacement in replacements.items():
        new_content = re.sub(pattern, replacement, new_content)
        
    # fix multiple spaces
    new_content = re.sub(r" +", " ", new_content)

    if new_content != content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        try:
            print(f"Cleaned {filepath}")
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

