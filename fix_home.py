
import os
import re

def process_file(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    replacements = {
        r"bg-slate-950": "bg-background",
        r"bg-slate-900": "bg-background",
        r"bg-slate-800": "bg-surface-container",
        r"bg-slate-700": "bg-surface-container-high",
        r"text-slate-200": "text-on-background",
        r"text-slate-300": "text-on-surface",
        r"text-slate-400": "text-on-surface-variant",
        r"text-slate-500": "text-on-surface-variant",
        r"border-slate-800": "border-outline-variant",
        r"border-slate-700": "border-outline",
        r"text-amber-500": "text-primary",
        r"bg-amber-500": "bg-primary",
        r"hover:bg-amber-400": "hover:bg-primary-fixed",
        r"hover:text-amber-400": "hover:text-primary-fixed",
        r"border-amber-500": "border-primary",
        r"hover:border-amber-500": "hover:border-primary",
        r"hover:border-amber-400": "hover:border-primary-fixed",
        r"text-slate-900": "text-on-primary",
        
        # fix typography flattening in home
        r"font-headline-xl text-xl lg:text-lg": "font-headline-xl text-4xl lg:text-6xl",
        r"font-headline-md text-xl lg:text-lg": "font-headline-md text-2xl lg:text-3xl",
        r"font-headline-lg text-lg": "font-headline-lg text-3xl",
        r"font-body-lg text-lg": "font-body-lg text-lg lg:text-xl",
        r"font-headline-md text-xl": "font-headline-md text-2xl",
        r"font-headline-md text-lg": "font-headline-md text-xl",
        r"font-headline-xl text-lg": "font-headline-xl text-3xl",
        r"font-label-sm text-sm": "font-label-md",
        r"text-lg lg:text-xl": "text-lg", # in case of double
        r"text-4xl lg:text-6xl text-white": "text-4xl lg:text-6xl text-on-background",
        r"text-white": "text-on-background",
    }
    
    new_content = content
    for pattern, replacement in replacements.items():
        new_content = re.sub(pattern, replacement, new_content)

    if new_content != content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)
        print("Updated home.blade.php")

process_file("resources/views/livewire/home.blade.php")

