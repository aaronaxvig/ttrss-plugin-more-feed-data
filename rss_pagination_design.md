# Overall idea
Archive blogs in TT-RSS using the pagination which Wordpress feeds support, using the ?paged=x query parameter.

# Observations
Wordpress feeds typically support pagination using a URL like this: /?feed=rss&paged=4.  Such a link gets a 301 redirect to /feed/?paged=4.  For Atom, /?feed=atom&paged=4 redirects to /feed/atom/?paged=4  All Wordpress feeds in TT-RSS appear to have already traversed this redirect and are stored as /feed/, so simply appending ?paged=4 to the end of the feed URL should get the job done.

By fetching page 2, and then page 3, etc. it is possible to collect all of the posts of a blog.

# Methodology

## Rate
I think it is reasonable to fetch one or two past feed pages alongside the normal request for the current feed.  This would double or triple the load, yes, but it is an incredibly small load still, and in the same order of magnitude.

## Control
Each retrieval attempt will be stored in a database table, including whether it succeeded.  A new retrieval attempt looks at the page number of the highest successful retrieval attempt and attempts to get the next page.

Any articles added in the meantime are not an issue as that simply causes some already retrieved posts to be pushed onto the next page and retrieved again.  Article deletion could result in skipped articles but I think it is sufficiently rare.  Such a problem could be 99% solved by always fetching pairs of pages.  For example fetching 2 and 3, and then on the next attempt fetching 3 and 4, then 4 and 5.  The 1% scenario would be if more than a page-worth of articles was deleted.

Eventually there will be no more pages left to retrieve.

## Storage
The retrieved feed pages are processed normally by TT-RSS and stored in the normally articles table.  I suppose they will default to unread, which I think is probably good.  Keeping them out of the Fresh special view would be nice though.