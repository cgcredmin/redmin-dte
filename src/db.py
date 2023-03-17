import os


def migrations():
    rollbacks = [
        '2023_03_16_112136_create_compras_table.php',
    ]
    for rollback in rollbacks:
        os.system(
            f'php artisan migrate:rollback --path=database/migrations/{rollback}')
    os.system('php artisan migrate')


def seed():
    os.system('php artisan db:seed')


migrations()
