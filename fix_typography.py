
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    def fix_class_attr(match):
        cls_str = match.group(1)
        classes = cls_str.split()
        
        has_font_xl = "font-headline-xl" in classes
        has_font_lg = "font-headline-lg" in classes
        has_font_lg_mobile = "font-headline-lg-mobile" in classes
        has_font_md = "font-headline-md" in classes
        
        # Remove any text-headline-* classes first to clean up
        new_classes = [c for c in classes if not c.startswith("text-headline-")]
        
        # Add the correct text-headline-* based on font-headline-*
        if has_font_xl:
            new_classes.append("text-headline-xl")
        elif has_font_lg:
            new_classes.append("text-headline-lg")
        elif has_font_lg_mobile:
            new_classes.append("text-headline-lg-mobile")
        elif has_font_md:
            new_classes.append("text-headline-md")
        else:
            new_classes = classes
            
        return "class=\"" + " ".join(new_classes) + "\""

    # Match class="..."
    new_content = re.sub(r"class=\"([^\"]+)\"", fix_class_attr, content)

    if new_content != content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        print("Restored typography in", filepath)

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

