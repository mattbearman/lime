#LiME - Less is More Engine#

LiME is a fast and simple online publishing platform, which can be used for blogs as well as normal web pages. LiME doesn't use a database to store and manage entries,
instead text files in markdown format are used. The entire website is complied into raw HTML files when ever you add or update an entry. This means no server side processing is required to server a page, the end result is a very quick website, which will respond well to heavy load (especially when combined with NGINX).

I built the LiME webpage complier in PHP for no other reason than it's the language I know best. In my mind LiME is more of an idea than a pice of software, and I'd love nothing more than to see it ported to other languages.

##Documentaion##
I will be creating a full LiME website with full documentation, but until then the source code and demo site should give you enough information to get going.

##Installing##
To use LiME simply clone this repository to your server, rename the demo_site/ directory to site/ and point your set your servers document root to site/webroot.
