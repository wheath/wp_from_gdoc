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
p.add_argument('-e', '--endstring')
p.add_argument('-v', '--verbosity')
p.add_argument('-r', '--refreshdoc')
p.add_argument('-z', '--zpagename')

verbose = False

opts = p.parse_args()

if opts.verbosity:
  verbose = True

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

if not opts.zpagename:
  arg_error = True
  print '-z is required'

if arg_error:
  print "example usage: wp_gen_from_gdoc.py -u xyz@google.com -p mypassword' -d 'google doc name' -s 'start string to begin parsing/converting to wp'"
  sys.exit(1)

pages = []

from BeautifulSoup import BeautifulSoup
import mechanize

def pprint_page(page_info):
  print "title: %s\n" % page_info['title']
  sections = page_info['after_title']
  for section in sections:
    for sec_key in section:
      print "section key: %s, value: %s" % (sec_key, section[sec_key])

def pprint_acf(acf_info):
  print "page_name: %s\n" % acf_info['page_name']
  sections = acf_info['sections']
  for section in sections:
    for sec_key in section:
      #print "section key: %s" % sec_key
      print "section key: %s, value: %s" % (sec_key, section[sec_key])

def convert_to_acf(page_info):
  acf_info = {}
  acf_info['page_name'] = page_info['title']
  acf_info['sections'] = []

  sections = page_info['after_title']

  headers = ['h1', 'h2', 'h3']
  non_headers = ['subtitle']
  ignores = ['h5', 'h6']
  content_tags = ['p', 'ul', 'table']

  print "_dbg num sections: %d" % len(sections) 
  print "_dbg page_info: " % page_info
  content_num = 0
  contents = ''
  for section in sections:
    for sec_key in section:
      print "section key: %s, value: %s" % (sec_key, section[sec_key])
      if sec_key not in ignores and sec_key in headers:
        section_info = {}
        contents = contents.strip()
        if contents:
          section_info['Section %d Content' % content_num] = contents
          acf_info['sections'].append(section_info)
          contents = ''

        content_num += 1
        section_info = {}
        section_info['Section %d Header %s' % (content_num, sec_key)] = section[sec_key].strip()
        acf_info['sections'].append(section_info)
      
      if sec_key not in ignores and sec_key in non_headers:
        section_info = {}
        section_info['Section %d %s' % (content_num, sec_key)] = section[sec_key]
        acf_info['sections'].append(section_info)

      if sec_key in content_tags:
        contents += section[sec_key]

  section_info = {}
  contents = contents.strip()
  if contents:
    section_info = {}
    section_info['Section %d Content' % content_num] = contents
    acf_info['sections'].append(section_info)
                   
  return acf_info 

def get_level_1_elements(html_lines):
  l1s = []
  html_str = ''.join(html_lines)
  #soup = BeautifulSoup('<html>' + html_str + '</html>')
  soup = BeautifulSoup(html_str)
  for child in soup.recursiveChildGenerator():
    #if child and  not child.isspace() and hasattr(child, 'parent') and child.parent == soup:
    if hasattr(child, 'parent') and child.parent == soup:
      try:
        #if child and not child.isspace():
        #if str(child).strip():
        name = getattr(child, "name", None)
        #if name is not None:
        if name is not None:
          print "_dbg name: %s" % name
          #if name == 'h2':
          l1s.append(child)    
      except:
        pass

  print "_dbg num l1s: %d" % len(l1s)

  #i = 0
  #for l1 in l1s:
  #while i < len(l1s):
    #l1 = l1s[i]
    #print "_dbg i: %d name: %s child: %s" % (i, l1.name, l1.text)
    #i+=1
  #sys.exit(1)
  return l1s

def filter_html_lines(opts, html_lines):
  s = 0
  start_found = False
  e = len(html_lines) - 1
  i = 0
  for l in html_lines:
    l = html_lines[i]
    if opts.startstring in l:
      s = i-5
      start_found = True
    i+=1
    if opts.endstring in l and start_found:
      e = i

  html_lines = html_lines[s:e]
  #print html_lines
  return html_lines

def add_manual_content(page_info):
  new_tag = {}
  new_tag['h1'] = 'Lower IT Costs With Customized IaaS Solutions'
  page_info['after_title'].insert(0, new_tag)

  new_tag = {}
  new_tag['subtitle'] = 'Providing Top-level, Personalized IT Solutions Custom-built To Suit Your Needs'
  page_info['after_title'].insert(1, new_tag)

def add_page(pages, page_info, opts):
  print '_dbg zpagename: %s' % opts.zpagename
  if page_info not in pages:
    print '_dbg page title: %s' % page_info['title']
    if opts.zpagename:
      if opts.zpagename == page_info['title'].strip():
        pages.append(page_info)
        return True
      else:
        print '_dbg did not append page title: %s' % page_info['title']
    else:
      pages.append(page_info)
  
     
def get_gdoc_as_html(docname):

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

  found = False
  if not feed.entry:
    if verbose:
      print 'No entries in feed.\n'
  for entry in feed.entry:
    if docname in entry.title.text:
      client.Download(entry, 'gdoc.html') 
      found = True

found = True
if found:
  br = mechanize.Browser()
  br.set_handle_robots(False)
  br.set_handle_equiv(False)
  br.addheaders = [('User-agent', 'Firefox')]


  import os.path
  if not os.path.isfile('gdoc.html') or opts.refreshdoc:
    get_gdoc_as_html(opts.docname)
  
  html_str = open('gdoc.html').read()
  soup = BeautifulSoup(html_str)
  html_pretty = soup.prettify()
  f = open('pretty.html', 'w')
  f.write(html_pretty)
  f.close() 
  if verbose:
    print html_pretty
  html_lines = html_pretty.split('\n')
  html_lines = filter_html_lines(opts, html_lines)
  if verbose:
    print '_dbg num html lines: %d' % len(html_lines)
    #print '_dbg html lines: %s' % html_lines
  #sys.exit(1)
  
  l1s = get_level_1_elements(html_lines)
  num_l1s = len(l1s)
  if verbose:
    print '_dbg num l1s: %d' % num_l1s


  cur_l1_num = 0
  start_found = False

  page_info = {}
  sections = []
  section_num = 0 
  section_content = []
  html_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'subtitle', 'p', 'ul', 'table'];

  for cur_l1 in l1s:
    #print "_dbg name: %s" % cur_l1.name 
    if cur_l1.name == 'p' and cur_l1.has_key('class') and  'subtitle' not in cur_l1['class'] and 'title' in cur_l1['class']:
      #print "_dbg title found"
      if page_info:
        print "_dbg attempt appending page name: %s" % page_info['title']
        if(add_page(pages, page_info, opts)):
          break;
      page_info = {}
      page_info['title'] = cur_l1.text
      page_info['after_title'] = []
   
    elif cur_l1.name in  html_tags and page_info:
      #print "_dbg non title found"
      #sys.exit(1)
      new_tag = {}
      if cur_l1.text.strip():
        print '_dbg cur_l1.name: %s' % cur_l1.name 
        if cur_l1.name == 'p':
          print '_dbg p found'
	  new_tag[cur_l1.name] = '<p>' + cur_l1.text + '</p>'
        elif cur_l1.name == 'ul':
          print "_dbg contents: %s" % cur_l1.contents
	  new_tag[cur_l1.name] = '<ul>' + ''.join(str(v) for v in cur_l1.contents) + '</ul>'
        else:
	  new_tag[cur_l1.name] = cur_l1.text
	page_info['after_title'].append(new_tag)      
	#print "_dbg current page: %s" % page_info

add_page(pages, page_info, opts)
print "_dbg num pages found: %d" % len(pages)

for page in pages:
  #print "_dbg current page: %s" % page_info
  if not page:
    continue
  print "_dbg page: %s" % pprint_page(page) 
  acf_info = convert_to_acf(page)
  print "_dbg acf info: %s" % json.dumps(acf_info)
  pprint_acf(acf_info)
  f = open('acf_info.json', 'w')
  f.write(json.dumps(acf_info))
  f.close() 
  #FIXME: assume one page at a time
  #if opts.endstring:
  #  break;
