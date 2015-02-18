#!perl

## This is a hack OK? So just get over it will you? Sheesh!

use strict;
use warnings;

use LWP::Simple;  # Talk to the web
use JSON;         # Manipulate JSON documents

use Data::Dumper; # For debugging



## See ES
my $es_url = '';



## See
## https://github.com/tokenly/tokenly-cms/tree/master/\
## www/slick/App/API/V1/Forum
my $api_url = 'https://letstalkbitcoin.com/api/v1/forum/threads';

my $start = 0;
my $limit = 100; # Arbitrary 'batch size'.

## Whitelist of keys we want:
my %keys_to_index =
    ( categoryName => 1,
      boardName => 1,
      threadId => 1,
      url => 1,
      title => 1,
      content => 1,
      userId => 1,
      views => 1,
      replies => 1,
      sticky => 1,
      locked => 1,
      postTime => 1,
      lastPost => 1,
      postId => 1,
    );

## There is an argument for simply indexing everything.

## TODO: Index user data.
## TODO: Work out how to link to user.



while(1){
    my $threads_doc =
        get "$api_url?start=$start&limit=$limit&no-profiles=1"
            or die "FFFF ($start) : $!\n";
    
    ## Remove the BOM http://en.wikipedia.org/wiki/Byte_order_mark
    $threads_doc =~ s/^\x{ef}\x{bb}\x{bf}//; 
    
    my $threads_doc_href = decode_json $threads_doc;

    ## We've run out of threads (is 'next' null)?
    last unless defined $threads_doc_href->{next};
    
    ## For each thread...
    for my $thread_href (@{$threads_doc_href->{threads}}){
        #print Dumper $thread_href;
        
        my $topic_id = $thread_href->{topicId};
        print $topic_id, "\n";
        
        ## Leave only the keys we care about
        for (keys %$thread_href){
            delete $$thread_href{$_}
            unless $keys_to_index{$_};
        }
        
        #print Dumper $thread_href;
        
        ## TODO: Send this JSON to an ES 'thread' document
        
        
        
        ## We now have to make a separate call per thread to get the
        ## replies... sheesh!
        
        ## Get the thread, including all replies. In theory we should
        ## page here like with threads, but I can't be bothered.
        my $thread_doc =
            get "$api_url/$topic_id?limit=9999&no-profiles=1"
                or die "\tFFFF ($topic_id) : $!\n";
        
        ## Remove the BOM http://en.wikipedia.org/wiki/Byte_order_mark
        $thread_doc =~ s/^\x{ef}\x{bb}\x{bf}//; 
        
        my $thread_doc_href = decode_json $thread_doc;
        
        for my $reply_href (@{$thread_doc_href->{replies}}){
            #print Dumper $reply_href;
            
            ## Leave only the keys we care about
            for (keys %$reply_href){
                delete $$reply_href{$_}
                unless $keys_to_index{$_};
            }
            
            #print Dumper $reply_href;
            
            ## TODO: Send this JSON to an ES 'reply' document
        }
        #exit;
    }
    
    ## Move on to the next batch
    $start = $threads_doc_href->{next};
    
    ## Debugging
    last if $start > 20;
}

warn "DONE\n";
