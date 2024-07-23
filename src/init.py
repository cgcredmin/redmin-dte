import os
from rich import print

# clear console command
os.system('clear')

base_path = "/var/www/html/"

# set working directory to base_path
os.chdir(base_path)

folders = ["storage/app", "storage/app/certs", "storage/app/pdf", "storage/app/db", "storage/app/tempfiles", "storage/app/tmp", "storage/app/xml", "storage/app/xml/dte",
           "storage/app/xml/folios", "storage/framework", "storage/framework/views", "storage/framework/cache", "storage/framework/cache/data",
           "storage/cache", "storage/cache/data", "storage/sessions", "storage/testing", "storage/views", "storage/logs", "tests"]
# loop through the array and check if the folder exists, if not, then create it and set permissions
print(":eyes:", ">> [blue]Checking folders...[/blue]")
for folder in folders:
    if not os.path.exists(folder):
        os.makedirs(folder)
        print(":white_check_mark:",
              "\t - [green]" + folder + " CREATED.[/green]")

    print(":key:", "\t - [blue]Setting permissions for " + folder + " ...[/blue]")
    os.chmod(folder, 0o777)
    os.chown(folder, 1000, 1000)

#fix permissions problem on first start
print(":sparkles:", ">> [blue]Fixing permissions...[/blue]")
os.chmod(base_path, 0o777)

