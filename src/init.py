import os
from rich import print

# clear console command
os.system('clear')

base_path = "/var/www/html/"

# set working directory to base_path
os.chdir(base_path)

folders = ["storage/app", "storage/app/certs", "storage/app/pdf", "storage/app/db", "storage/app/tempfiles", "storage/app/tmp", "storage/app/xml", "storage/app/xml/dte",
           "storage/app/xml/folios", "storage/framework", "storage/cache", "storage/cache/data", "storage/sessions", "storage/testing", "storage/views", "storage/logs", "tests"]
# loop through the array and check if the folder exists, if not, then create it and set permissions
print(":eyes:", ">> [blue]Checking folders...[/blue]")
for folder in folders:
    if not os.path.exists(folder):
        os.makedirs(folder)
        print(":white_check_mark:",
              "\t - [green]" + folder + " CREATED.[/green]")
        os.chmod(folder, 0o777)
        # os.chown(folder, "root", "root")

# read environment variables
DB_CONNECTION = os.getenv('DB_CONNECTION')
DB_DATABASE = os.getenv('DB_DATABASE')

# check if DB_CONNECTION is set
if DB_CONNECTION is None:
    print(":exclamation:", ">> [bold red]DB_CONNECTION is not set[/bold red]")
else:
    # if DB_CONNECTION is set to sqlite, then check if the db file exists
    if DB_CONNECTION == "sqlite":
        if not os.path.isfile(DB_DATABASE):
            open(DB_DATABASE, 'a').close()
            print(":white_check_mark:",
                  "\t - [green]" + DB_DATABASE + " CREATED.[/green]")
            os.chmod(DB_DATABASE, 0o777)

# #fix permissions problem on first start
print(":sparkles:", ">> [blue]Fixing permissions...[/blue]")
os.chmod(base_path, 0o777)

# #run composer install
print(":rocket:", ">> [blue]Running composer install...[/blue]")
os.execl("/usr/local/bin/composer", "composer", "install", "--ignore-platform-reqs")

# execute db.py
# os.system('python3 db.py')
