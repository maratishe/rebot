Rebot comes from REcommendation BOT -- a software robot which operates semi-automatically, via recommendations. 

The idea echoes a recent advance in robot building called "social robotics", where robots learn what they have to do by socializing with their human guides.  "Guided learning" is another term for this. 


## Overview 

This project is a collection of parts. Some of the parts are ongoing research projects.  However, there is a reason why Rebot has to be one whole thing -- its practical usecase is ... well ... *too practical* -- the temptation to put it all in one box is just too strong.  

The parts are
 1. Recommendation Engine
 2. Javascript API for Dropbox (does not officially exist) so that webapps can have access to it. 
 3. Visualization components where RING and METROMAP are the two key ones.
 4. A new indexing engine called Stringex -- this one is OK with the index accessed over the network -- say Dropbox.
 5. Chrome Extension which can merge all the above in one package. 
    

Each part is considered separately further on. 

## 1. Recommendation Engine

Early idea is expressed in [https://github.com/maratishe/trainsofsought][1] as a separate research project. The idea is opposed to Facets which might be helpful in contextual search but not holistically enough.  The idea -- Trains of Sought (ToS) is a holistic approach and also a process involving continuous exchange between the system and its human users.  Recommendation engine was only mentioned in this early idea, while this project puts it to practical use. 

This code has 3 files related to recommendation engine:

 - `requireme.php`  -- all the productivity PHP necessities and utilities packed as one huge file
 - `bayes.php`  -- some code I borrowed and modified to operate in continuous learning and allow multiple classes per training/classification. The code is a Naive Bayes Classifier. 
 - `classifiers.php`   -- already part of `require.php` but just in case you want see how it operates.  Very simple engine with only `train()` and `classify()` functions.  Very well commented. 

## 2. Javascript API for Dropbox 

See `03.cloudstorage.js` at https://github.com/maratishe/chrome.testcases for the actual Dropbox API written in JS.  Dropbox does not have an official JS API, but knowing the headers it is not too hard to implement on.  The code at https://github.com/maratishe/chrome.testcases has the test code separately from the API itself -- just load the extension in your Chrome. 

## 3. Visualization Components -- Ring and Metromap

A bit more tricky.  *Ring* is a part of https://github.com/maratishe/chrome.testcases -- called Nicecover.RING (the name is a long story).  *Ring* is a nice way to show aggregates of things which can be grouped based on some features.  So, basically a 2D visualization -- the ring is one dimension and grouping is the other. The form is very nice when it needs to be interactive -- it already is, in fact.  Written in SVG. 

*Metromaps* are trickier.  You can use [Graphviz][2] when you have a backend, but with Dropbox API above you might want to stay inside the browser.  So, `metromap.*` files in this project are an attempt to generate simple by visually pleasing layouts.  See `metromap.notes.pdf` for a small description of the problem and `metromap.layout2.pdf` for example layouts. 

This part is incomplete. But I am working on it. You can see the shape of things, though. 


## 4. The Stringex Indexer

This is a separate project at https://github.com/maratishe/stringex but `stringex.php` in this code has the latest copy.  Do not forget to require `requireme.php` when running the code.  The strongest feature of the indexer is your ability to browse its context in traffic-efficient manner -- this is important when your index is sitting in Dropbox or any other over-the-network place. 


## 5. Chrome Extension

This is easy.  https://github.com/maratishe/chrome.testcases is a ready-made extension which you can run.  Also see my [blog][3] on the topic.  It is slightly bigger than just the extension because it talks about browser-based software robots. 


 
> Written with [StackEdit](https://stackedit.io/).


  [1]: https://github.com/maratishe/trainsofsought
  [2]: http://www.graphviz.org/
  [3]: http://practicalclouds.blogspot.jp/2013/12/how-to-build-robot-in-browser.html