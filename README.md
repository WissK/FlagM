# How to use

1- Set up the two databases "online-db" and "not-online" found in the master branch in the docs folder.
2- Open the postman collection prepared also in the master branch in the docs folder
3- Run the application (preferably on port 8080) in the master branch

# Application Description

This application allows to create/read/update/delete records into the offline-db and synchronize the records to an online database. There are two ways to synchronize:
  1- By calling the "9- Synchronize Offine to Online" request in postman.
  2- By calling the "9- Call api from another app to sync" request in postman. If you wish to use this method you will have to run the other application "flagmtask online" on another port 8000. the second app is on (https://github.com/WissK/FlagM2)
 
 Once the synchronization is done. The admin will receive an email (you can change the receipient of the email by changing the first parameter of the function sendVerificationEmail on line 208.
 
 # How does the sync work ?
 
 in the 'not-online'.orders table, there's a field called sync which is set to 0 if a record is newly inserted or updated. this field will be used to move the inserts and the updates to the online db. And for the deleted records, they will be removed from the online database if not found in the offline one.
 All the synched records will have the sync field set to 1.
