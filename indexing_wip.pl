#!perl

## This is a hack OK? So just get over it will you? Sheesh!

use strict;
use warnings;

use LWP::Simple; # Talk to the web
use JSON;        # Manipulate JSON documents

## See
## https://github.com/tokenly/tokenly-cms/tree/master/\
## www/slick/App/API/V1/Forum
my $api_url = 'https://letstalkbitcoin.com/api/v1/forum/threads';

my $start = 0;
my $limit = 100; # Arbitrary 'batch size'.

while(1){
    my $threads_doc = get "$api_url?start=$start&limit=$limit&no-content=1&no-profiles=1"
        or die "FFFF ($start) : $!\n";
    
    ## Remove the BOM http://en.wikipedia.org/wiki/Byte_order_mark
    $threads_doc =~ s/^\x{ef}\x{bb}\x{bf}//; 
    
    my $threads_doc_href = decode_json $threads_doc;

    ## We've run out of threads (is 'next' null)?
    last unless defined $threads_doc_href->{next};
    
    ## For each thread...
    for my $thread_href (@{$threads_doc_href->{threads}}){
	## TODO: Take thread, board and category information from this
	## document...
        #print $_, "\n" for keys %$thread_href;
	
	## We have to make a separate call per thread to get the
	## replies... sheesh!
	my $topic_id = $thread_href->{topicId};
        print $topic_id, "\n";
	
	## Get the thread, including all replies
	my $thread_doc = get "$api_url/$topic_id?limit=99999&no-profiles=1"
	    or die "\tFFFF ($topic_id) : $!\n";
	
	## Remove the BOM http://en.wikipedia.org/wiki/Byte_order_mark
	$thread_doc =~ s/^\x{ef}\x{bb}\x{bf}//; 
	
	my $thread_doc_href = decode_json $thread_doc;
	
	for my $reply_href (@{$thread_doc_href->{replies}}){
	    ## Get the content of the replies from this document
	    #print "\t$_\n" for keys %$reply_href;
	}
    }
    
    ## Move on to the next batch
    $start = $threads_doc_href->{next};
    
    ## Debugging
    last if $start > 20;
}

warn "DONE\n";
