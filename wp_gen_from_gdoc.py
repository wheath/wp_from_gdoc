import gdata.docs.service
import sys
import json
import os
import re
import argparse

p = argparse.ArgumentParser(description="parse command line args")

p.add_argument('-u', '--user')
p.add_argument('-p', '--password')
p.add_argument('-d', '--docname')
p.add_argument('-s', '--startstring')

opts = p.parse_args()

arg_error = False

if not opts.user:
  arg_error = True
  print '-u is required'

if not opts.password:
  arg_error = True
  print '-p is required'

if not opts.startstring:
  arg_error = True
  print '-s is required'

if not opts.docname:
  arg_error = True
  print '-d is required'

if arg_error:
  print "example usage: wp_gen_from_gdoc.py -u xyz@google.com -p mypassword' -d 'google doc name' -s 'start string to begin parsing/converting to wp'"
  sys.exit(1)

pages = []

from BeautifulSoup import BeautifulSoup
import mechanize

def get_header_info(html_lines, cur_line_num):  

  print '_dbg in get_header_info'
  print '_dbg cur_line_num 1: %d' % cur_line_num
  cur_line = html_lines[cur_line_num]
  print '_dbg initial cur_line: %s' % cur_line
  title = ''
  cur_line_num +=1
  cur_line = html_lines[cur_line_num]
  print '_dbg cur_line: %s' % cur_line
  if 'name' in cur_line:
    cur_line_num = cur_line_num+2
    cur_line = html_lines[cur_line_num]
    if 'span' in cur_line:
      cur_line_num = cur_line_num+1
      title = html_lines[cur_line_num]  
      return title, cur_line_num+2

  return '', cur_line_num

def get_raw_info(html_lines, cur_line_num, tag_name):  
  end_tag = "</%s>" % tag_name
  while cur_line_num < len(html_lines) and end_tag  not in cur_line:
    raw_str = html_lines[cur_line_num]
    cur_line_num += 1

  return raw_str, cur_line_num
    
  

def get_title(html_lines, cur_line_num):  
  print '_dbg in get_title'
  print '_dbg cur_line_num 2: %d' % cur_line_num
  return get_header_info(html_lines, cur_line_num)

def get_tag(html_lines, cur_line_num, after_title, tag_name):
  if 'h' in tag_name:
    print '_dbg about to call get_header_info'
    print '_dbg cur_line_num 3: %d' % cur_line_num
    tag_str, cur_line_num = get_header_info(html_lines, cur_line_num)
  else:
    tag_str, cur_line_num = get_raw_info(html_lines, cur_line_num, tag_name)

  if tag_str:
    new_tag = {}
    new_tag[tag_name] = tag_str
    after_title.append(new_tag)      

  return cur_line_num
  

def get_after_title(html_lines, cur_line_num, after_title):
  print 'in get_after_title' 
  cur_line = html_lines[cur_line_num]
  print '_dbg cur_line: %s' % cur_line
  while cur_line_num < len(html_lines) and ' title' not in cur_line:
    print '_dbg checking cur_line: %s' % cur_line
    if '<h1' in cur_line:
      print "_dbg about to call get_tag"
      print '_dbg cur_line_num 4: %d' % cur_line_num
      cur_line_num = get_tag(html_lines, cur_line_num, after_title, 'h1')
    cur_line_num += 1
    cur_line = html_lines[cur_line_num]

  if ' title' in cur_line:
    cur_line_num += -1

  return cur_line_num


def get_page_info(html_lines, cur_line_num):  
  page_info = {}
  #try:
  title, cur_line_num = get_title(html_lines, cur_line_num)  
  if title:
    page_info['title'] = title
    page_info['after_title'] = []
    after_title = page_info['after_title']
    print '_dbg about to call get_after_title'
    if cur_line_num + 1 < len(html_lines):
      print '_dbg cur_line_num 5: %d' % cur_line_num 
      cur_line_num = get_after_title(html_lines, cur_line_num+1, after_title)  
  #finally:
  return page_info, cur_line_num

def get_gdoc_as_html():

  # Create a client class which will make HTTP requests with Google Docs server.
  client = gdata.docs.service.DocsService()
  client.email = opts.user
  client.password = opts.password
  client.source = 'gdoc to wp'

  client.ProgrammaticLogin()

  q = gdata.docs.service.DocumentQuery()
  q['title'] = opts.docname
  q['title-exact'] = 'true'
  feed = client.GetDocumentListFeed()

  found = false
  if not feed.entry:
    print 'No entries in feed.\n'
  for entry in feed.entry:
    if opt.docname in entry.title.text:
      client.Download(entry, 'gdoc.html') 
      found = True

found = True
if found:
  br = mechanize.Browser()
  br.set_handle_robots(False)
  br.set_handle_equiv(False)
  br.addheaders = [('User-agent', 'Firefox')]

  html_str = open('gdoc.html').read()
  soup = BeautifulSoup(html_str)
  html_pretty = soup.prettify()
  #print html_pretty
  #sys.exit(1)
  html_lines = html_pretty.split('\n')
  num_html_lines = len(html_lines)
  print '_dbg num lines: %d' % len(html_lines)

  cur_line_num = 0
  start_found = False
  
  while cur_line_num < num_html_lines:
    cur_line = html_lines[cur_line_num]
    if not start_found:
      if opts.startstring in cur_line:
        start_found = True  
      cur_line_num += 1
      continue
    
    #try:
    if ' title' in cur_line in cur_line: 
      page_info, cur_line_num = get_page_info(html_lines, cur_line_num)  
      if page_info:
	pages.append(page_info)
    #finally:
    cur_line_num += 1

print "_dbg num pages found: %d" % len(pages)
for page in pages:
  print "_dbg page: %s" % page 
