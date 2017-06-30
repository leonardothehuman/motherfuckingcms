function uglify(html){
  var outhtml = '';
  var insidetag = false;
  var insidepre = false;
  var inside1q = false;
  var inside2q = false;
  var lastchar = '';
	for (var i=0; i < html.length; i++){
  	if((html.charAt(i) == '>') && (inside1q != true) && (inside2q != true)){
    	insidetag = false;
    }
  	if(html.charAt(i) == '<'){
    	insidetag = true;
      if(html.substring(i+1, i+4).toUpperCase() == 'pre'.toUpperCase()){
      	insidepre = true;
      }
      if(html.substring(i+1, i+5).toUpperCase() == '/pre'.toUpperCase()){
      	insidepre = false;
      }
    }
    if(insidetag == true){
    	if(html.charAt(i) == '"'){
      	inside2q = !inside2q;
      }
      if(html.charAt(i) == "'"){
      	inside1q = !inside1q;
      }
    }
    var nextchar = html.charAt(i);	
    var copythis = true;
   
    if((insidetag != true) && (insidepre != true) && (inside1q != true) && (inside2q != true)){
    	if(/[\s\n\t\r]/igm.test(html.charAt(i))){
      	nextchar = ' ';
        while((i < html.length - 1) && (/[\s\n\t\r]/igm.test(html.charAt(i + 1)))){
        	i++;
        }
      }
      
    }
    outhtml += nextchar;
  }
	return outhtml;
}
function prettyfy(html){
  var outhtml = '';
  var insidetag = false;
  var insidepre = false;
  var insideEnd = false;
  var isexclude = false;
  var tabcount = 0;
  var exclude = ['a','b','big','i','small','tt','abbr','acronym','cite','code','dfn','em','kbd','strong','samp','time','var','bdo','br','img','map','object','q','script','span','sub','button','input','label','select','textarea','!--'];
  var inside1q = false;
  var inside2q = false;
	for (var i=0; i < html.length; i++){
  	if(insidetag == true){
    	if(html.charAt(i) == '"'){
      	inside2q = !inside2q;
      }
      if(html.charAt(i) == "'"){
      	inside1q = !inside1q;
      }
    }
  	if((html.charAt(i) == '<') && (inside1q != true) && (inside2q != true) && (insidepre != true)){
    	insidetag = true;
    	for (var k = 0; k < exclude.length; k++){
      	if(
        		(html.substring(i + 1, i + 2 + exclude[k].length).toUpperCase() == exclude[k].toUpperCase()+' ') || 
            (html.substring(i + 1, i + 2 + exclude[k].length).toUpperCase() == exclude[k].toUpperCase()+'>') ||
            (html.substring(i + 1, i + 3 + exclude[k].length).toUpperCase() == '/'+exclude[k].toUpperCase()+' ') ||
            (html.substring(i + 1, i + 3 + exclude[k].length).toUpperCase() == '/'+exclude[k].toUpperCase()+'>')
          ){
        	isexclude = true;
        }
      }
      if(isexclude == false){
        if(html.charAt(i + 1) == '/'){
          insideEnd = true;
          tabcount--;
          outhtml += '\n';
          for(var j = 0; j < tabcount; j++){
            outhtml += '  ';
          }
        }else{
          insideEnd = false;
          tabcount++;
          outhtml += '\n';
          for(var j = 0; j < tabcount; j++){
            outhtml += '  ';
          }
        }
      }
    }
    
  	outhtml += html.charAt(i);
    if((html.charAt(i) == '>') && (inside1q != true) && (inside2q != true) && (insidepre != true)){
    	insidetag = false;
    	if(isexclude == false){
        if(insideEnd == true){
          tabcount--;
          outhtml += '\n';
          for(var j = 0; j < tabcount; j++){
            outhtml += '  ';
          }
        }else{
          tabcount++;
          outhtml += '\n';
          for(var j = 0; j < tabcount; j++){
            outhtml += '  ';
          }
        }
      }
      isexclude = false
      insideEnd = false;
    }
    if(html.charAt(i) == '<'){
      if(html.substring(i+1, i+4).toUpperCase() == 'pre'.toUpperCase()){
      	insidepre = true;
        tabcount--;
      }
    }
    if(html.charAt(i) == '<'){
      if(html.substring(i+1, i+5).toUpperCase() == '/pre'.toUpperCase()){
      	insidepre = false;
        tabcount--;
      }
    }
  }
	return outhtml;
}


function autoindent(html){
	var regx = html.match(/<\!--.*:.*-->/igm);
	var addTags = '';
	for(var i = 0; i < regx.length; i++){
		addTags += regx[i]+'\n';
	}
	html = html.replace(/<\!--.*:.*-->[\n\r]/igm,'');
    html = html.replace(/<\!--.*:.*-->/igm,'');
	html = prettyfy(uglify(html));
	return addTags+html;
}
