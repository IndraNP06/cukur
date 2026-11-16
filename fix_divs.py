import os, re
files_to_fix = [
    r'livewire\admin\dashboard.blade.php',
    r'livewire\admin\kinerja.blade.php',
    r'livewire\admin\staf.blade.php',
    r'livewire\customer\profil.blade.php',
    r'livewire\customer\riwayat.blade.php',
    r'livewire\staff\dashboard.blade.php',
    r'livewire\auth\login.blade.php',
    r'livewire\auth\register.blade.php',
    r'livewire\auth\forgot-password.blade.php',
    r'livewire\auth\reset-password.blade.php',
    r'livewire\customer\ulasan.blade.php',
]

root_dir = r'c:\Users\Indra\OneDrive\Desktop\skripsi1\ran_cukurin_app\resources\views'
for file_path in files_to_fix:
    full_path = os.path.join(root_dir, file_path)
    if os.path.exists(full_path):
        with open(full_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        def replacer(match):
            class_content = match.group(1)
            if 'flex-grow' not in class_content:
                new_class = 'flex-grow flex flex-col ' + class_content
                return f'<div class="{new_class}"'
            return match.group(0)
            
        new_content = re.sub(r'<div\s+class="([^"]*)"', replacer, content, count=1)
        
        if new_content != content:
            with open(full_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f'Updated {file_path}')
