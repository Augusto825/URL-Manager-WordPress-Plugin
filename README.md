# URL-Manager-WordPress-Plugin
This plugin creates a tab in the left sidebar of the WordPress dashboard where you can upload links, and uploads the CSV file and individual URLs.
The upload process is the same as creating a custom post.
When uploading, the plugin creates a new table if there is no corresponding table in the database and stores the URLs of the CSV file.
At the same time, it creates a CSV file that is exactly the same as the table in wp-content/URL-manager-2024.
When you upload the CSV file again, it reads the contents of the file, compares it with the table, stores the non-duplicated ones in the table, and creates a new CSV file that only has non-duplicated URLs.
