# Store Info

The class get the info more important of an app from google play or itunes by url app.

## Return info

* title
* description
* icon
* images
* videos
* avg
* num_votes

## Running the tests

The file test_store.php you can use for testing the class by terminal. You have to pass like argument the url of the app.

Examples:

For play.google: 
```
php test_store.php https://play.google.com/store/apps/details?id=com.easybrain.sudoku.android
```
For itunes: 
```
php test_store.php https://itunes.apple.com/es/app/hello-stars/id1403455040?mt=8&v0=WWW-EUES-ITSTOP100-FREEAPPS&l=es&ign-mpt=uo%3D4
```