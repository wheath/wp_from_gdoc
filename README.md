wp_from_gdoc
============

Created a wordpress site with templates, acf fields from a google doc

example usage of generating wp from google doc:

python wp_gen_from_gdoc.py -u <your user id>@gmail.com -p <your google password> -d 'Title of google doc' -s 'start string of google doc to begin processing into a wp site'

added a wp-cli acf command in file yaacf.php.  Example usage:

wp --require=/yourpath/wp_from_gdoc/yaacf.php yaacf field move  --field_label='Section 1 Header' --field_order_no=0 --field_from_group_id=116 --field_to_group_id=119
