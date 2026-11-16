
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    new_content = content
    
    # Text sizing downgrades
    text_replacements = {
        r"\btext-9xl\b": "text-7xl",
        r"\btext-8xl\b": "text-6xl",
        r"\btext-7xl\b": "text-5xl",
        r"\btext-6xl\b": "text-4xl",
        r"\btext-5xl\b": "text-3xl",
        r"\btext-4xl\b": "text-2xl",
        r"\btext-3xl\b": "text-xl",
        r"\btext-2xl\b": "text-lg",
        
        # Some custom font scales I saw might be missed by previous scripts
        r"\btext-headline-xl\b": "text-headline-lg",
        r"\btext-headline-lg\b": "text-headline-md",
    }
    
    # Image/Element sizing downgrades
    # w-64 -> w-48, w-48 -> w-32, w-32 -> w-24, w-24 -> w-16, w-16 -> w-12
    # h-64 -> h-48, h-48 -> h-32, h-32 -> h-24, h-24 -> h-16, h-16 -> h-12
    size_replacements = {
        r"\bw-96\b": "w-72",
        r"\bh-96\b": "h-72",
        r"\bw-80\b": "w-64",
        r"\bh-80\b": "h-64",
        r"\bw-72\b": "w-56",
        r"\bh-72\b": "h-56",
        r"\bw-64\b": "w-48",
        r"\bh-64\b": "h-48",
        r"\bw-48\b": "w-32",
        r"\bh-48\b": "h-32",
        r"\bw-32\b": "w-24",
        r"\bh-32\b": "h-24",
        r"\bw-24\b": "w-16",
        r"\bh-24\b": "h-16",
        r"\bw-20\b": "w-14",
        r"\bh-20\b": "h-14",
        r"\bw-16\b": "w-12",
        r"\bh-16\b": "h-12",
    }

    # Apply text replacements
    for pattern, replacement in text_replacements.items():
        new_content = re.sub(pattern, replacement, new_content)
        
    # Apply size replacements
    for pattern, replacement in size_replacements.items():
        new_content = re.sub(pattern, replacement, new_content)

    if new_content != content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        try:
            print(f"Scaled down {filepath}")
        except:
            pass

def main():
    target_dirs = [
        "resources/views"
    ]
    for d in target_dirs:
        for root, dirs, files in os.walk(d):
            for file in files:
                if file.endswith(".blade.php"):
                    process_file(os.path.join(root, file))

if __name__ == "__main__":
    main()

