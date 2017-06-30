//addEventListener polyfill 1.0 / Eirik Backer / MIT Licence
(function(win, doc){
	if(win.addEventListener)return;		//No need to polyfill

	function docHijack(p){var old = doc[p];doc[p] = function(v){return addListen(old(v))}}
	function addEvent(on, fn, self){
		return (self = this).attachEvent('on' + on, function(e){
			var e = e || win.event;
			e.preventDefault  = e.preventDefault  || function(){e.returnValue = false}
			e.stopPropagation = e.stopPropagation || function(){e.cancelBubble = true}
			fn.call(self, e);
		});
	}
	function addListen(obj, i){
		if(i = obj.length)while(i--)obj[i].addEventListener = addEvent;
		else obj.addEventListener = addEvent;
		return obj;
	}

	addListen([doc, win]);
	if('Element' in win)win.Element.prototype.addEventListener = addEvent;			//IE8
	else{		//IE < 8
		doc.attachEvent('onreadystatechange', function(){addListen(doc.all)});		//Make sure we also init at domReady
		docHijack('getElementsByTagName');
		docHijack('getElementById');
		docHijack('createElement');
		addListen(doc.all);	
	}
})(window, document);
//End of the polyfill
window.addEventListener('load', function(){
    //Create the buttons for the editor
	var cmdPrefix = '<button onmousedown="document.execCommand';
    document.getElementById('wysiwyg-buttons').innerHTML = ''+
    cmdPrefix + '(\'bold\',false);event.preventDefault();"><b>B</b></button>'+
    cmdPrefix + '(\'italic\',false);event.preventDefault();"><i>I</i></button>'+
    cmdPrefix + '(\'strikeThrough\',false);event.preventDefault();"><s>S</s></button>'+
    cmdPrefix + '(\'underline\',false);event.preventDefault();"><u>U</u></button>'+
    cmdPrefix + '(\'createLink\',false,window.prompt(\'Type a url\',\'\'));event.preventDefault();">Link</button>'+
    cmdPrefix + '(\'unlink\',false);event.preventDefault();">Unlink</button>'+
    cmdPrefix + '(\'fontName\',false,window.prompt(\'Type a font name\',\'\'));event.preventDefault();">Font</button>'+
    cmdPrefix + '(\'fontSize\',false,window.prompt(\'Type a font size (1-7)\',\'\'));event.preventDefault();">Font Size</button>'+
    cmdPrefix + '(\'justifyCenter\',false);event.preventDefault();">Center</button>'+
    cmdPrefix + '(\'justifyFull\',false);event.preventDefault();">Justify</button>'+
    cmdPrefix + '(\'justifyLeft\',false);event.preventDefault();">Left</button>'+
    cmdPrefix + '(\'justifyRight\',false);event.preventDefault();">Right</button>'+
    cmdPrefix + '(\'insertImage\',false,window.prompt(\'Type a image url\',\'\'));event.preventDefault();">Image</button>'+
    cmdPrefix + '(\'insertHorizontalRule\',false);event.preventDefault();">HR</button>'+
    cmdPrefix + '(\'insertOrderedList\',false);event.preventDefault();">OL</button>'+
    cmdPrefix + '(\'insertUnorderedList\',false);event.preventDefault();">UL</button>'+
    cmdPrefix + '(\'insertParagraph\',false);event.preventDefault();">P</button>'+
    cmdPrefix + '(\'subscript\',false);event.preventDefault();">Sub</button>'+
    cmdPrefix + '(\'superscript\',false);event.preventDefault();">Sup</button>'+
    cmdPrefix + '(\'removeFormat\',false);event.preventDefault();">Unformat</button>'+
    cmdPrefix + '(\'undo\',false);event.preventDefault();">Undo</button>'+
	cmdPrefix + '(\'indent\',false);event.preventDefault();">Indent</button>'+
    cmdPrefix + '(\'redo\',false);event.preventDefault();">Redo</button><br />';
    //Create the buttons to change the view
    document.getElementById('wysiwyg-changer').innerHTML = ''+
    '<button id="goVisual">Visual</button>'+
    '<button id="goCode">Code</button>';
    //Create the editable area
    document.getElementById('wysiwyg-wrapper').innerHTML = '<div class="wysiwyg" id="wysiwyg" contenteditable="true"></div>';
    //Function that transfers the content from the textarea to the visual editor
    var towysiwyg = function(){
        //Transfer content
        document.getElementById('wysiwyg').innerHTML = document.getElementById('filecontent').value;
        var tofind = document.getElementById('filecontent').value;
        //Backup the special tags, because it will be deleted
        var regx = tofind.match(/<\!--.*:.*-->/igm);
        addTags = '';
        for(var i = 0; i < regx.length; i++){
            addTags += regx[i]+'\n';
        }
    };
    //Function that transfers the content from the visual editor to the textarea
    var toarea = function(){
        //Ensure that the special tags has been erased, because we will restore from the previous backup
        document.getElementById('wysiwyg').innerHTML = document.getElementById('wysiwyg').innerHTML.replace(/<\!--.*:.*-->[\n\r]/igm,'');
        document.getElementById('wysiwyg').innerHTML = document.getElementById('wysiwyg').innerHTML.replace(/<\!--.*:.*-->/igm,'');
        //Transfer content and the special tags from backup
        document.getElementById('filecontent').value = addTags+document.getElementById('wysiwyg').innerHTML;
    };
    //Function that goes to the visual editor
    var goVisual = function(){
        //Hide and show the apropriate elements
        document.getElementById('wysiwyg-wrapper').style.display = 'block';
        document.getElementById('wysiwyg-buttons').style.display = 'block';
        document.getElementById('file-editor').style.display = 'none';
        towysiwyg();
    };
    document.getElementById('goVisual').addEventListener('click', goVisual,false);
    //Function that goes to the code editor
    document.getElementById('goCode').addEventListener('click', function(){
        //Hide and show the apropriate elements
		if(document.getElementById('wysiwyg-wrapper').style.display == 'block'){//Run this only if we are on wysiwyg mode
			document.getElementById('wysiwyg-wrapper').style.display = 'none';
			document.getElementById('wysiwyg-buttons').style.display = 'none';
			document.getElementById('file-editor').style.display = 'block';
			toarea();
		}
    },false);
    var addTags = '';//Variable to store the special tags
    //Editor should start on visual mode
    goVisual();
},false);