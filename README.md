Backup Service
==============

This library is used to create an encrypted backup archive of one or more folders on your server and one or more MongoDb databases.
Once the backup archive is created, it will be stored to the defined S3 bucket. 

The library automatically manages the backups on the S3 bucket and it only uploads the backup archive once. If required to keep a weekly,
monthly or yearly, using the S3 API backup copies are made, so we don't need to re-upload the same archive multiple times.
 
The backup is  a `tar gzip` archive, which is then encrypted with `gpg` with the defined passphrase and only then, in this encrypted state,
is transferred to the S3 bucket.
  
Installation
---------------------
The best way to install the component is using Composer.

```bash
composer require webiny/backup-service
```
For additional versions of the package, visit the [Packagist page](https://packagist.org/packages/webiny/backup-service).
  

## Configuration
  
To run the backup script, just create a small PHP script and point it to your config file:
```php
<?php

require_once '../vendor/autoload.php';

$service = new \Webiny\BackupService\Service(__DIR__.'/SampleConfig.yaml');
$service->createBackup();

```

You can run the script via CLI or you can configure a cron job.
 
As for the configuration parameters, here is an example config. 

```yaml
BackupService:
    Folders:
        - /var/www/site1.com
        - /var/www/site2.com
    MongoDatabases:
        BackupTest1:
            Host: 127.0.0.1:27017
            Database: BackupTest1
            Username: Admin
            Password: password
        BackupTest2:
            Host: 127.0.0.1:27017
            Database: BackupTest2
    Frequency: # daily backup is always on
        - Week
        - Month
    TempPath: "/tmp/backups/"
    Passphrase: "test-password"
    S3:
        RemotePath: "Backups/"
        AccessId: # S3 access id
        AccessKey: # S3 access key
        Bucket: # bucket where to store the backups
        Region: # AWS region name where your bucket is located, eg eu-central-1
```

- `Folders`: contain one or more folders that will be added to the backup archive.
- `MongoDatabases`: a list of mongo databases that should be exported (using mongodump) and they will also be included in the backup archive.
- `Frequency`: by default the script keeps a 24h backup snapshot and a 48h snapshot. You can additionally add a `weekly`, `monthly` and `yearly` snapshot.
- `TempPath`: this is a writable path on the local machine where the script will place some temporary files as well as some logs that you can later reference and see what the script has been doing.
- `Passphrase`: this is the passphrase that will be used by the `gpg` script to encrypt the archives.
- `S3`: this is your S3 configuration. Note: make sure you get the AWS region name correctly, otherwise the script will hang on the upload process (http://docs.aws.amazon.com/general/latest/gr/rande.html)

## Logs

The library, on each run, generates some logs in the `{TempPath}/logs` folder. You can reference those logs to see how the backup process went and was it successful or not.

## License and Contributions

Contributing > Feel free to send PRs.

License > [MIT](LICENSE)


## Bugs and improvements

Just report them under issues, or even better, send a pull request :)