
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    new_content = content
    # Loop to collapse duplicate adjacent tailwind classes like "lg:p-5 lg:p-5"
    classes_to_collapse = [
        "lg:p-4", "lg:p-5", "lg:p-6", "lg:p-8",
        "md:p-4", "md:p-5", "md:p-6", "md:p-8",
        "sm:p-4", "sm:p-5", "sm:p-6", "sm:p-8",
        "lg:gap-4", "lg:gap-5", "lg:gap-6", "lg:gap-8",
        "md:gap-4", "md:gap-5", "md:gap-6", "md:gap-8",
        "lg:px-4", "lg:px-5", "lg:px-6", "lg:px-8",
        "lg:py-4", "lg:py-5", "lg:py-6", "lg:py-8",
        "md:px-4", "md:px-5", "md:px-6", "md:px-8",
        "md:py-4", "md:py-5", "md:py-6", "md:py-8",
        "p-4", "p-5", "p-6", "p-8",
    ]
    
    for cls in classes_to_collapse:
        pattern = r"\b" + re.escape(cls) + r"(?:\s+" + re.escape(cls) + r")+\b"
        new_content = re.sub(pattern, cls, new_content)

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

