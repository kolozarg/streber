<?php if(!function_exists('startedIndexPhp')) { header("location:../index.php"); exit;}
# streber - a php5 based project management system  (c) 2005 Thomas Mann / thomas@pixtur.de
# Distributed under the terms and conditions of the GPL as stated in lang/license.html

/**
 * classes related to rendering html output
 *
 * called from:
 *
 *
 * @author: Thomas Mann
 * @uses:
 * @usedby:
 *
 */


require_once(confGet('DIR_STREBER') . "render/render_misc.inc.php");
require_once(confGet('DIR_STREBER') . "render/render_block.inc.php");

/**
* pagefunctions for editing the currently displayed obj (eg. Delete a task)
*
*/
class PageFunction extends BaseObject
{
    public $target;             # pageid without params
    public $params;             # pageid without params
    public $url;                # link-target (pageid including params)
    public $name;               # name
    public $icon;               # name of function icon
    public $parent_block;
    public $tooltip;
    public $context_menu=false; # show in context-menus
    public $key;                # used as id in assoc. array  'functions'
}

/**
* group page function with keyword like "*new:* *status:*" etc.
*/
class PageFunctionGroup extends PageFunction
{

}


/**
* link in TopNavigation
*/
abstract class NaviLink
{
    public $name        = '';
    public $target_id   = '';       # id of internal target-page / used to get url and for automatically highlighting option
    public $target_url  = '';       # target url including parameters / build from id and target_params
    public $tooltip;                # optional
    public $active=false;           # hightlight as current option
    public $target_params=array();  # assoc. array of target-params
    public $type;                   # e.g. 'project', 'task' (addes as additional style-class to a)

    public function __construct( $args)
    {
        global $PH;

        #--- set parameters ---
        foreach($args as $key=>$value) {
            is_null($this->$key);   # cause E_NOTICE if member not defined
            $this->$key= $value;
        }

        #--- hilight active option ---
        if($this->target_id == $PH->cur_page_id) {
            $this->active= true;
        }

        #--- check for valid page ids ----
        #if(!$PH->getValidPage($this->target_id)) {
        #    trigger_error(" could not get page_id of '$this->target_id'<br>", E_USER_WARNING);
        #}

        #--- get url if not already defined ------
        if(!$this->target_url) {
            if(!isset($this->target_id)) {
                trigger_error("NaviOption::__construct() needs either target_id or target_url", E_USER_ERROR);
            }
            $this->target_url= $PH->getUrl($this->target_id, &$this->target_params);
        }

        #--- get name, if not already defined ----
        if(!$this->name && $this->target_id) {
            if(isset($PH->hash[$this->target_id])) {
                $this->name = $PH->hash[$this->target_id]->title;
            }
        }
    }
}

class NaviCrumb extends NaviLink
{

    public function render()
    {
        global $PH;
        $str_tooltip= $this->tooltip
            ? "title=\"" . asHtml($this->tooltip) . "\""
            : '';

        #--- hide if note a valid link ----
        if(isset($this->target_id) && $this->target_id != "") {
            if($PH->getValidPage($this->target_id)) {

                $additional_class= $this->type;

                if($this->active) {
                    return "<a class=\"current $additional_class\" href=\"{$this->target_url}\" $str_tooltip>" . asHtml($this->name). "</a>";
                }
                else {
                    if($additional_class) {
                        return "<a class=\"$additional_class\" href=\"{$this->target_url}\" $str_tooltip>". asHtml($this->name) . "</a>";
                    }
                    else {
                        return "<a href=\"{$this->target_url}\" $str_tooltip>". asHtml($this->name) . "</a>";
                    }
                }
            }
        }
        #--- external link ------------
        else {
            return "<a href=\"{$this->target_url}\" $str_tooltip>". asHtml($this->name) ."</a>";
        }
    }
}

class NaviOption extends NaviLink
{
    public $separated;

    public function render()
    {
        global $PH;
        $str_tooltip= $this->tooltip
            ? "title=\"". asHtml($this->tooltip)."\""
            : '';

        #--- hide if note a valid link ----
        if(isset($this->target_id) && $this->target_id != "") {

            if($PH->getValidPage($this->target_id)) {

                if($this->active) {
                    return "<a class=\"current\" href=\"{$this->target_url}\" $str_tooltip>".asHtml($this->name)."</a>";
                }
                else {
                    return "<a href=\"{$this->target_url}\" $str_tooltip>". asHtml($this->name)."</a>";
                }
            }
        }
        #--- external link ------------
        else {
            return "<a href=\"{$this->target_url}\" $str_tooltip>".asHtml($this->name)."</a>";
        }
    }
}



class Page
{

    #--- members -----
    public  $section_scheme ='misc';    # color-scheme of the active tab. set by renderHeaderTabs() (effects sub_navigtaion) ('projects'|'time'|etc.)
    public  $content_open   =false;      # open content-table
    public  $title          ='';
    public  $title_minor    ='';
    public  $title_minor_html;  # for inserting html-code (e.g. links) use this buffer
    public  $type           ='';
    public  $tabs;              # assoc. array with tab-definition
    public  $cur_tab;
    public  $options;           # assoc. array with NaviOptions-definition
    public  $crumbs;            # assoc. array with breadcrumb-definition
    public  $cur_crumb;         # for overwriting active breadcrumb
    public  $html_close;
    public  $content_col;
    public  $use_jscalendar =false;
    public  $autofocus_field=false;
    public  $functions      =array();
    public  $content_columns=false;
	public  $format         = FORMAT_HTML;
	public  $extra_header_html = '';

    #--- constructor ---------------------------
    public function __construct($args=NULL)
    {

        ### set global page-var
        global $_PAGE;
        global $auth;
        global $PH;
        if(isset($_PAGE) && is_object($_PAGE)) {
        	trigger_error("'page' global var already defined!", E_USER_NOTICE);
        }
        $_PAGE= $this;

        ### set default-values ###
        $this->content_open=false;    # open content-table

        $sq= get('search_query');
        $old_search_query= get('search_query') && get('search_query') !=""
            ? 'value="'. asHtml($sq). '"'
            : '';

		if(get('format') && get('format') != ''){
		    $this->format = get('format');
		}

        $this->tabs=array(
        	"home"		=>array(
                'target'=>$PH->getUrl('home'),
                'title'=>isset($auth->cur_user->nickname)
                       ? $auth->cur_user->nickname
                       : 'Home',

                #'title'=>__("<span class=accesskey>H</span>ome"),
                'bg'=>"misc"       ,
                'accesskey'=>'h',
                'tooltip'=>__('Go to your home. Alt-h / Option-h')
            ),
        	"projects"	=>array(
                'target'    => $PH->getUrl('projList',array()),
                'title'     =>__("<span class=accesskey>P</span>rojects"),
                'html'=>   buildProjectSelector(),
                'tooltip'   =>__('Your projects. Alt-P / Option-P'),
                'bg'        =>"projects",
                'accesskey' =>'p'
            ),
        	"people"    =>array(
                'target'    =>$PH->getUrl('personListAccounts',array()),
                'title'     =>__("People"),
                'tooltip'   =>__('Your related People'),
                'bg'        =>"people"
            ),
        	"companies"  =>array(
                'target'    =>$PH->getUrl('companyList',array()),
                'title'     =>__("Companies"),
                'tooltip'   =>__('Your related Companies'),
                'bg'        =>"people"
            ),
        	#"calendar"=>array(
            #    'target'    =>"index.php?go=error",
            #    'title'     =>__("Calendar"),
            #    'bg'        =>"time"
            #),
           	"search"    =>array(
                'target'    =>'javascript:document.my_form.go.value=\'search\';document.my_form.submit();',
                'title'     =>__("<span class=accesskey>S</span>earch:&nbsp;"),
                'html'      =>'<input accesskey=s '.$old_search_query.' name=search_query onFocus=\'document.my_form.go.value="search";\'>',

                'tooltip'   =>__("Click Tab for complex search or enter word* or Id and hit return. Use ALT-S as shortcut. Use `Search!` for `Good Luck`")
            )
        );          # assoc. array with tab-definition
        $this->cur_tab="";
        $this->options=array();       # assoc. array with options-definition
        $this->crumbs=array();        # assoc. array with breadcrumb-definition

        ### set params ###
        if($args) {
            foreach($args as $key=>$value) {
                empty($this->$key);        #cause notification for unknown keys
                $this->$key= $value;
            }
        }

        ### put out header, some js-functions and styles for proper error-display
    }

    function __set($nm, $val)   {
        if (isset($this->$nm)) {
           $this->$nm = $val;
       } else {
            trigger_error("can't set page->$nm", E_USER_WARNING);
       }
    }

    #--- get --------------------------------------
    function __get($nm)
    {
       if (isset($this->$nm)) {
           return $r;
       } else {
            trigger_error("can't read $nm", E_USER_WARNING);
       }
   }


    /**
    * add a page function
    */
    function add_function(PageFunction $fn)
    {
        global $PH;


        if($fn instanceof PageFunctionGroup) {
            $this->functions[$fn->name]=$fn;
            $fn->parent_block= $this;
            return;
        }
        ### cancel, if not enough rights ###
        if(!$PH->getValidPageId($fn->target)) {

            /**
            * it's quiet common that the above statement returns NULL. Do not warn here
            */
            #trigger_error("invalid target $fn->target", E_USER_WARNING);
            return;
        }


        ### build url ###
        if(!$fn->url= $PH->getUrl($fn->target, $fn->params)) {

            /**
            * it's quiet common that the above statement returns NULL. Do not warn here
            *
            * e.g. if links have been disabled for current user
            */
            #trigger_error("invalid for page function target $fn->target", E_USER_WARNING);
            return NULL;
        }


        ### create key ###
        $key=count($this->functions);
        if(isset($fn->target)) {
            $key= $fn->target;
        }
        else if(isset($fn->id)){
            $key= strtolower($fn->id);
        }

        ### warn, if already defined? ###
        if(isset($this->functions[$key])) {
            trigger_error("overwriting function with id '$key'", E_USER_NOTICE);
        }

        ### if not given, get title for page-handle ###
        if(!isset($fn->name)) {
            $phandle=$PH->getValidPage($fn->target);
            $fn->name= $phandle->title;

        }

        $this->functions[$key]=$fn;
        $fn->parent_block= $this;
    }

	function print_presets($args=NULL){
		global $PH;

		if(isset($args) && count($args) == 4){
			$preset_location = $args['target'];
			$project_id = $args['project_id'];
			$preset_id = $args['preset_id'];
			$presets = $args['presets'];

			echo "<div class=\"presets\">";
			#echo __("Filter-Preset:");
			foreach($presets as $p_id=>$p_settings) {
				if($p_id == $preset_id) {
					echo $PH->getLink($preset_location, $p_settings['name'], array('prj'=>$project_id,'preset'=>$p_id),'current');
				}
				else {
					echo $PH->getLink($preset_location, $p_settings['name'], array('prj'=>$project_id,'preset'=>$p_id));
				}
			}
			echo "</div>";
		}
		else{
			trigger_error("cannot get arguments", E_USER_NOTICE);
		}
	}
}

#========================================================================================
# PageElement
#========================================================================================
# - all other elements of a page extend this class
# - maps the global var $page
class PageElement extends BaseObject
{
    public $page;
    public $children=array();
    public $name;
    public $title;

    #--- constructor--------------------------------
    public function __construct($args=NULL)
    {
        if($args) {
            foreach($args as $key=>$value) {
                is_null($this->$key);   # cause E_NOTICE if member not defined
                $this->$key=$value;
            }
        }

        global $_PAGE;

        if(!isset($_PAGE) || !is_object($_PAGE)) {
            trigger_error("Cannot create PageElement s without Page-object", E_USER_WARNING);
        }
        else {
            $this->page= $_PAGE;
        }
    }

    #--- render -------------------------------------
    # note: derived classes should not implement render() but __toString()
    public function render(&$arg=false)
    {
        if($arg) {
            return $this->__toString($arg);
        }
        else {
            return $this->__toString();
        }
    }


    public function add(PageElement $child)
    {
        if($child->name) {
            $this->children[$child->name]= $child;
        }
        else{
            $this->children[]=$child;
        }
    }
}


#========================================================================================
# HTML Start
#
#========================================================================================
class PageHtmlStart extends PageElement {

    public function __toString()
    {
        global $auth;

        ### include theme-config ###
        if($theme_config= getThemeFile("theme_config.inc.php")) {
            require_once($theme_config);
        }
        header("Content-type: text/html; charset=utf-8");
        $title= asHtml($this->page->title) . '/'. asHtml($this->page->title_minor).' - ' . confGet('APP_NAME');
        $buffer= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'

        .'<html>'
        .'<head>'
        .'<meta http-equiv="Content-type" content="text/html; charset=utf-8">';
        if(isset($auth->cur_user->language)) {
            $buffer.='<meta http-equiv="Content-Language" content="'.$auth->cur_user->language.'">';
        }
        $buffer.='<META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE">'
        .'<META HTTP-EQUIV="EXPIRES" CONTENT="-1">'
        .'<META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">'
        .'<link rel="SHORTCUT ICON" href="./favicon.ico">'
        ."<title>$title</title>";

        /**
        * use Starlight syntax highlighting if enabled and client uses Gecko
        */
        if(confGet('LINK_STAR_LIGHT') && preg_match("/Gecko/i", $_SERVER['HTTP_USER_AGENT'],$matches)) {
            $buffer.= "<link rel=\"stylesheet\" href=\"themes/starlight/star-light.css\" type=\"text/css\"/>";
        }

        $buffer.= "<link rel=\"stylesheet\" title=\"top\" media=\"screen\" type=\"text/css\" href=\"". getThemeFile("styles.css") . "?v=" . confGet('STREBER_VERSION') ."\">";

        ### link print-style ###
        if(confGet('LINK_STYLE_PRINT')) {
            $buffer.="<link rel=\"stylesheet\" media=\"print, embossed\" type=\"text/css\" href=\"". getThemeFile("styles_print.css") . "?v=".confGet('STREBER_VERSION')."\">";
        }
        ### link alternative style (for development only) ###
        if(0) {
            $buffer.= "<link rel=\"alternate stylesheet\" title=\"Mozilla Lefthand\" media=\"screen\" type=\"text/css\"  href=\"themes/".getCurTheme()."/styles_left.css\">";
        }


        $buffer.='
		<script type="text/javascript" src="js/jquery.js' . "?v=" . confGet('STREBER_VERSION') . '"></script>
		<script type="text/javascript" src="js/jeditable.js' . "?v=" . confGet('STREBER_VERSION') . '"></script>
		<script type="text/javascript" src="js/misc.js' . "?v=" . confGet('STREBER_VERSION') . '"></script>
		<script type="text/javascript" src="js/listFunctions.js'. "?v=" . confGet('STREBER_VERSION') . '"></script>
		<script type="text/javascript">
        <!--

            //------ on load -------
            //$(document).ready(function(){
			window.onload = function()
            {';

        if($this->page->autofocus_field) {
            $buffer.="
document.my_form." . $this->page->autofocus_field. ".focus();
document.my_form." . $this->page->autofocus_field. ".select();";
        }

        $buffer.='initContextMenus();
                ';

	            if($q=get('q')) {
	                $q= asCleanString($q);
	                if($ar = explode(" ",$q)) {
	                    foreach($ar as $q) {
                            $buffer.= "highlightWord(document.getElementsByTagName('body')[0],'$q'); ";
	                    }
	                }
	                else {
	                    $buffer.= "highlightWord(document.getElementsByTagName('body')[0],'$q'); ";
	                }
	            }

	            $buffer.= "misc();
	                       listFunctions();

			}

        //-->
		</script>
		<script type=\"text/javascript\" src=\"js/contextMenus.js\"></script>
        <script type=\"text/javascript\" src=\"js/searchhi.js\"></script>
        <script type=\"text/javascript\">
            cMenu.menus=new Object();
        </script>";

        /**
        * for notes on searchi see: http://www.kryogenix.org/code/browser/searchhi/
        */

        ### add calendar-functions for form-pages ###
        # NOTE: including calendar tremedously increases loading time!
        if($this->page->use_jscalendar) {
            $buffer.= '<style type="text/css">@import url(' . getThemeFile('/calendar-win2k-1.css') . ');</style>'
            . '<script type="text/javascript" src="js/calendar.js"></script>'
            . '<script type="text/javascript" src="js/lang/calendar-en.js"></script>'
            . '<script type="text/javascript" src="js/calendar-setup.js"></script>'
            . '<script type="text/javascript" src="js/dragslider.js"></script>';
        }

        ### add extra html ###
        $buffer.= $this->page->extra_header_html;

        $buffer.= "
        </head>";
        $buffer.='<body ';
        global $PH;
        if(isset($PH->cur_page_id)) {
            $buffer.= "class=\"$PH->cur_page_id\"";
        }


        #$buffer.="updateTableColor();";
        $buffer.='>'; # close body tag & onload
        $buffer.= "<div class=\"noscript\"><noscript>";
        $buffer.= __("This page requires java-script to be enabled. Please adjust your browser-settings.");
        $buffer.="</noscript></div><div id=\"outer\">";

        return $buffer;
    }
}







#========================================================================================
# HTML End
#========================================================================================
class PageHtmlEnd extends PageElement {

    public function __toString()
    {
		switch($this->page->format){
			case FORMAT_CSV:
				$buffer = '';
				break;

			default:
				$buffer = $this->PageHtmlEndAsHTML();
				break;
		}

		return $buffer;

    }

	private function PageHtmlEndAsHTML()
	{
        $buffer="";
        $footer= new PageFooter;
        $buffer.= $footer->render();
    	$buffer.= "</div><div id=\"sideboard\"><div></div></div></body></html>";
        return $buffer;
    }
}

#========================================================================================
# Quick new
#========================================================================================
class PageQuickNew extends PageElement {

    public function __toString() {
        $buffer=
            '<div id="quicknew" title="Add to selected task(s). Shortcut:ALT-N">'
            .'<input type="hidden" name="noedit" value="0">'
            .'<input type="hidden" name="newtype" value="task">'
            .'<label><span class="accesskey">n</span>ew </label>'
            .'<select name="type">'
            .'<option value="task" selected>' . __("Task") . '</option>'
            .'<option value="effort">' . __("Effort") .      '</option>'
            .'<option value="comment">'. __("Comment") .     '</option>'
            .'</select>'
            .'<input class="inp" accesskey="n" size="50" name="new_name">'
            .'<input class="button" type="button" value ="'. __("Add Now") .'" onclick="javascript:document.my_form.go.value=\'quickNew\';document.my_form.noedit.value=\'1\';document.my_form.submit();">'
            .'<input class="button" type="button" value ="'. __("Edit") . '" onclick="javascript:document.my_form.go.value=\'quickNew\';document.my_form.submit();">'
            #."<span class=help><a href=''>help</a></span>"
            .'<span class="hint">E.g. <b>BUG: application crashes on IE !! 4h in 5 days</b>  (Creates task labeled \'bug\', prio1, with estimated 4 hours and due in 4 days)</span>'
            .'</div>'
            ;
        global $PH;
        $PH->go_submit='quickNew';


        return $buffer;
    }

}




#========================================================================================
# Header
#========================================================================================
class PageHeader extends PageElement
{

    public function __toString()
    {
		switch($this->page->format){
			case FORMAT_CSV:
				$buffer = '';
				break;
			default:
				$buffer = $this->PageHeaderAsHTML();
				break;
		}

		return $buffer;
	}

	private function PageHeaderAsHTML(){
        global $PH;
        global $auth;


        echo(new PageHtmlStart);

        $logout_url=$PH->getUrl('logout');
        $app_url= confGet('APP_PAGE_URL');

        $submit_url= confGet('USE_MOD_REWRITE')
                ? 'submit'
                : 'index.php';

        $buffer= '<form name="my_form" action="'. $submit_url .'" method="post" enctype="multipart/form-data" >';
        $buffer.="\n<div id=\"header\">
            	<div id=\"logo\">";
    	$buffer.="<div class=\"text\">"
                ."<a title=\"" . confGet('APP_NAME') ." - free web based project management\" href=\"$app_url\">"
                .confGet('APP_TITLE_HEADER')
                ."</a>"
                ."</div>".
            	"</div>";

        ### account if logged in ###
        if($auth->cur_user) {

            ### login / register if anonymous user ###
            if($auth->cur_user->id == confGet('ANONYMOUS_USER')) {
                $buffer.="<div id=\"user_functions\">"
            		   ."<span class=\"features\">"
        			   . "<b>".$PH->getLink('loginForm',__('Login'),array()). "</b>";
                if(confGet('REGISTER_NEW_USERS')) {
    			   $buffer  .= "<em>|</em>"
    			            .  $PH->getLink('loginForm',__('Register'),array());
    			}
                $buffer.= "</span>"
            	       .  "</div>";

            }

            ### account / logout ###
            else {
                $link_home= $PH->getLink('personView',$auth->cur_user->name,array('person'=>$auth->cur_user->id),'name');;

                $buffer.="<div id=\"user_functions\">"
        			   . "<span class=\"user\">". __("you are"). " </span>"
        			   . $link_home
        			   ."<em>|</em>"
            		   ."<span class=\"features\">"
                      #. $PH->getLink('personEdit',__('Profile'),array('person'=>$auth->cur_user->id))
                      #. "<em> | </em>"
                      . "<a href=\"$logout_url\">" . __("Logout") ."</a>"
                      . "</span>"
            	      . "</div>";
            }
        }
        else if(confGet('REGISTER_NEW_USERS')) {
                $buffer.="<div id=\"user_functions\">"
            		   ."<span class=\"features\">"
        			   ."<b>". $PH->getLink('personRegister',__('Register'),array()) . "</b>"
                      . "</span>"
            	      . "</div>";

        }

    	$tabs= new PageHeaderTabs;

        $buffer.= $tabs->render();
        #echo(new PageHeaderTabs);
    	$buffer.="</div>";


        $crumbs= new PageHeaderNavigation;                   # breadcrumbs and options

        $buffer.=$crumbs->render();


        #--- write message ---
        global $PH;
        if($PH->messages) {
            $buffer.='<div class="messages">';
            foreach($PH->messages as $m) {
                if(is_object($m)) {
                    $buffer.= $m->render();
                }
                else {
                    $buffer.="<p>$m</p>";
                }
            }
            $buffer.='</div>';
        }

        $title=new PageTitle;
        $buffer.=$title->render();

        $functions= new PageFunctions();
        $buffer.= $functions->render();   # actually this should be a string-context for __toString , but it isn't ???

        return $buffer;
    }
}


#========================================================================================
# Header >> Tabs
#========================================================================================
class PageHeaderTabs extends PageElement {

    public function __toString()
    {
    #	global $tabs, $cur_tab, $str, $header_cur_tab_bg;

    	$buffer= '<ul id="tabs">';

    	$tab_found=false;
        if(!isset($this->page->tabs) || !is_array($this->page->tabs)) {
            trigger_error("tabs not defined", E_USER_WARNING);
            return;
        }

        $page=$this->page;
        foreach($page->tabs  as $tab=>$values){

      		$bg=	isset($values['bg'])
                ? $values['bg']
                : "misc";
    		$active="";

            /**
            * ignore tabs with out target (e.g. disable links)
            */
    		$target= isset($values['target'])
    		       ? $values['target']
    		       : '';
    		if(!$target) {
    		    continue;
    		}

    		#--- current tab ----
    		if($tab === $this->page->cur_tab) {
    			$active="current";
    			$page->section_scheme= $bg;
                $tab_found=true;
    		}
    		else {
                $bg.= "_shade"; # shade non-active tabs
    		}
    		$bg= "bg_$bg";

            $accesskey= isset($values['accesskey'])
                ? $accesskey='accesskey="'.$values['accesskey'].'" '
                : "";

    		$tooltip= isset($values['tooltip'])
                ? 'title="'. asHtml($values['tooltip']).'" '
                : "";

            $html= isset($values['html'])
                ? $html= $values['html']
                : "";
            $active==""
                ? $buffer.= "<li id=\"tab_{$tab}\" class=\"{$bg}\" $tooltip>\n"
                : $buffer.= "<li id=\"tab_{$tab}\" class=\"{$active} {$bg}\" $tooltip>\n";
            $buffer.= "<a href=\"$target\"  $accesskey>";
            $buffer.= $values['title'];
            $buffer.= '</a>';
            $buffer.= $html;
    	}
    	$buffer.= '</ul>';
        if(!$tab_found) {
            trigger_error("Could not find tab '{$this->page->cur_tab}' in list...", E_USER_NOTICE);
        }

        return $buffer;
    }
}   # end of PageHeaderTabs

#========================================================================================
# PageHeaderCrumbs
#========================================================================================
class PageHeaderNavigation extends PageElement
{
    public function __toString() {
        global $PH;

        $scheme=$this->page->section_scheme;
        $buffer="<div id=\"nav_sub\" class=\"bg_$scheme\">";


        ### look for active naviLink ###
        $active_navi_link= NULL;
        foreach($this->page->crumbs as $l) {

            if(!$l instanceof NaviLink) {
                trigger_error("navigation link as invalid type (added string instead of NaviLink-Object?)",E_USER_NOTICE);
            }

            ### overwrite active crumb-setting for tasks
            else if($l->target_id == $this->page->cur_crumb) {
                $l->active = true;
                $active_navi_link= $l;
                break;
            }
            else if($l->target_id == $PH->cur_page_id) {
                $l->active = true;
                if($active_navi_link) {
                    $active_navi_link->active=false;
                }
                $active_navi_link= $l;
            }
        }
        foreach($this->page->options as $l) {
            ### overwrite active crumb-setting for tasks
            if($l->target_id == $this->page->cur_crumb) {
                $l->active = true;
                $active_navi_link= $l;
                break;
            }
            else if($l->target_id == $PH->cur_page_id) {
                $l->active = true;
                if($active_navi_link) {
                    $active_navi_link->active=false;
                }
                $active_navi_link= $l;
            }
        }

    	if($this->page->crumbs) {

            ### breadcrumbs ###
    		$buffer.= '<span class="breadcrumbs">';

            ### go up ###
            $count=count($this->page->crumbs)-2;
            while($count >=0) {

                if($str=$this->page->crumbs[$count]->target_url) {
                   $buffer.= '<a class="up" href="'.$str.'" title="'.__('Go to parent / alt-U').'" accesskey="u">^</a>';
                    break;
                }
                $count--;
            }

            $sep_crumbs="";
            $page=$this->page;

            $count=0;       # count added crumbs to mark the last crumb as current
            $style="";
            foreach($page->crumbs as $crumb) {

                if($crumb instanceOf NaviCrumb) {
                    if($str= $crumb->render($active_navi_link == $crumb )) {
                        $buffer.=  $sep_crumbs . $str;
                        $sep_crumbs="<em>&gt;</em>";
                    }
                }
            }

            if($this->page->options) {
        	    $buffer.= $sep_crumbs;
            }
    		$buffer.= "</span>";
    	}

    	### options ###
    	if($this->page->options) {
    		$buffer.= '<span class="options">';
            $page= $this->page;
            $tmp_counter=0;                 # HACK! just to highlight a dummy breadcrump to test GUI-style

			$sep_options= "";
            foreach($page->options as $option) {

                $tmp_counter++;

                if($option instanceOf NaviOption) {
                    if($option->separated) {
                        $buffer.= '<em class="sep">|</em>';
                    }
                    else {
                        $buffer.= $sep_options;
                    }
                    $buffer.= $option->render();
                }
                else {
                    trigger_error(sprintf("NaviOption '%s' is has invalid type",$option),E_USER_WARNING);
                }
                $sep_options ="<em>|</em>";
            }
    		$buffer.= "</span>";
    	}

    	### wiki link ###
		$buffer .='<span class="help">'
                .'<a href="'
                .confGet('STREBER_WIKI_URL') . $PH->cur_page_id
                .'" title="' .__('Documentation and Discussion about this page')
                .'">'
                .__('Help')
                .'</a></span>';

        $buffer.="</div>";
        return $buffer;
    }


}

class PageHeaderCrumbs extends PageElement
{

    public function __toString() {
        $scheme=$this->page->section_scheme;
        $buffer="<div id=\"nav_sub\" class=\"bg_$scheme\">";
        if($this->page->crumbs) {

            ### breadcrumbs ###
            $buffer.= '<span class="breadcrumbs">';

            ### go up ###
            $count=count($this->page->crumbs)-2;
            while($count >=0) {
                $str=$this->page->crumbs[$count];

                if(preg_match("/ref=\"([^\"]*)\"/", $str,$matches)) {
                   $buffer.= '<a class="up" href="'.$matches[1].'" title="'.__('Go to parent / alt-U').'" accesskey="u">^</a>';
                    break;
                }
                $count--;
            }


            $sign="";
            $page=$this->page;

            $count=0;       # count added crumbs to mark the last crumb as current
            $style="";
            foreach($page->crumbs as $crumb) {

                if($crumb) {
                    $count++;
                    if($count == count($page->crumbs)) {
                        $style='class="current"';

                    }
                    if($crumb != '|') {
                        $buffer.= $sign.asHtml($crumb);
                    }
                    $sign="<em>&gt;</em>";
                }
            }
    		$buffer.= "</span>";
    	}

    	### options ###
    	if(@$this->page->options) {
    		$buffer.= '<span class="options">';
            $page= $this->page;
            $tmp_counter=0;                 # HACK! just to highlight a dummy breadcrump to test GUI-style

			$sep= "";
            foreach($page->options as $option) {
                $tmp_counter++;

                if($option instanceOf NaviOption) {
                    $buffer.= $option->render();
                    $buffer.= $sep;
                }
                else {
                    trigger_error(sprintf("NaviOption '%s' is has invalid type",$option),E_USER_WARNING);
                }
                $sep ="<em>|</em>";

     /*           #--- active? ---
                if($tmp_counter == 1) {
                    $buffer.= "<li class=\"current\">$option";
                }
                else {
                    if($option == "") {
                        $buffer.= "<li class=\"separator\">|";
                    }
                    else {
                        $buffer.= "<li>$option";
                    }
                }
                */
            }
    		$buffer.= "</span>";
    	}
        $buffer.="</div>";
    #    $buffer.= "</div>";
    #    $buffer.= "<div id=\"nav_sub\" class=\"$this->page->header_cur_tab_bg\"> ";
        return $buffer;
    }
}



#========================================================================================
# PageTitle
#========================================================================================
class PageTitle extends PageElement {


    public function __toString() {
        $buffer="";

       	$buffer.= '<div id="headline">';
    	if($this->page->type) {
    		$buffer.= '<div class="type">'. $this->page->type. '</div>';
    	}
    	$buffer.= '<h1 class="title">'. asHtml($this->page->title);
    	if($this->page->title_minor_html) {
    		$buffer.= '<span class="minor"> / '. $this->page->title_minor_html. '</span>';
    	}
    	else if($this->page->title_minor) {
    		$buffer.= '<span class="minor"> / '. asHtml($this->page->title_minor). '</span>';
    	}
    	$buffer.= "</h1>";
    	$buffer.= "</div>";


        return $buffer;
    }
}


#========================================================================================
# PageFunctions
#========================================================================================
class PageFunctions extends PageElement {


    public function __toString() {
        $buffer="";

        $buffer.='<div class="page_functions">';
        if($this->page->functions) {

            $count= count($this->page->functions);
            foreach($this->page->functions as $key=>$fn) {

                $class_last= (--$count == 0)
                           ? 'class="last"'
                           : '';

                if($fn instanceOf PageFunctionGroup) {
                    $buffer.='<span class="group">'. $fn->name. '</span>';
                }
                else {
                    if($fn->tooltip) {
                	    $buffer.="<a $class_last href=\"$fn->url\" title=\"$fn->tooltip\">";
                    }
                    else {
                	    $buffer.="<a $class_last href=\"$fn->url\">";
                    }


                	#if($fn->icon) {
                	#    $buffer.="<img src=\"". getThemeFile("/icons/". $fn->icon . ".gif") . "\">";
                	#}

                	$buffer.="$fn->name</a>";
                }
            }
        }
        $buffer.="</div>";
        return $buffer;
    }
}


#===========================================================================================
# PageContentStart
#===========================================================================================
class PageContentOpen extends PageElement
{

    public function __toString()
    {
		switch($this->page->format){
			case FORMAT_CSV:
				$buffer = '';
				break;

			default:
				$buffer = $this->PageContentOpenAsHTML();
				break;
		}

		return $buffer;
    }

	private function PageContentOpenAsHTML()
	{
        global $PH;

        if($this->page->content_open) {
            trigger_error("Content-table has already been opened. Wrong HTML-Structure? ", E_USER_WARNING);
        }
        $this->page->content_col=1;
        $this->page->content_open=true;
        $buffer="";

        ### pass from-handle? ###
        if(!$PH->cur_page_md5) {
            if(!($PH->cur_page_md5= get('from')) && !$PH->cur_page->ignore_from_handles) {
                #trigger_error("this page doesn't have a from-handle", E_USER_WARNING);       # this drops too many warnings in unit-tests
                $foo= true;
            }
        }
        else {
            $buffer.='<input type="hidden" name="from" value="'.$PH->cur_page_md5.'">';
        }

        $buffer.= '<div id="layout">';
        return $buffer;
    }
}

#===========================================================================================
# PageContentStart_Columns
#===========================================================================================
class PageContentOpen_Columns extends PageElement
{

    public function __toString()
    {

        global $PH;
        if($this->page->content_open) {
            trigger_error("Content-table has already been opened. Wrong HTML-Structure? ", E_USER_WARNING);
        }
        $this->page->content_col=1;
        $this->page->content_open=true;
        $this->page->content_columns=true;
        $buffer="";
        #$buffer.= '<form name="my_form" action="index.php" method="post">';

        ### pass from-handle? ###
        if(!$PH->cur_page_md5) {
            if(!$PH->cur_page_md5= get('from')) {
                trigger_error("this page doesn't have a from-handle", E_USER_NOTICE);
            }
            $buffer.='<input type="hidden" name="from" value="'.$PH->cur_page_md5.'">';
        }
        else {
            $buffer.='<input type="hidden" name="from" value="'.$PH->cur_page_md5.'">';
        }

        $buffer.= '<div id="layout">';
        $buffer.= '<div id="c1">';
        return $buffer;
    }
}

#===========================================================================================
# PageContentClose
#===========================================================================================
class PageContentClose extends PageElement
{

    public function __toString()
    {
		switch($this->page->format){
			case FORMAT_CSV:
				$buffer = '';
				break;

			default:
				$buffer = $this->PageContentCloseAsHTML();
				break;
		}

		return $buffer;
	}

	private function PageContentCloseAsHTML(){
        global $PH;
        if(!$this->page->content_open) {
            trigger_error("No content-table to close. Wrong HTML-structure?", E_USER_NOTICE);
        }
        $this->page->content_open= false;

        $buffer="";
        $buffer.= "</div>";

        if($this->page->content_columns) {
            $buffer.= "</div>";
        }

        $go= $PH->go_submit
             ? $PH->go_submit
             : 'home';

        $buffer.= '<input type="hidden" name="go" value="'.$go.'">';
        $buffer.= "</form>";


        return $buffer;
    }
}

class PageContentNextCol extends PageElement {
    public function __toString() {
        if(!$this->page->content_open) {
            trigger_error("No content-table to close. Wrong HTML-structure?", E_USER_NOTICE);
        }
        $this->page->content_col++;
        #$buffer="</td><td id=\"c{$this->page->content_col}\">";
        $buffer='</div><div id="c2">';
        return $buffer;
    }
}


class PageFooter extends PageElement
{


    public function __toString()
    {
        global $TIME_START;
        global $DB_ITEMS_LOADED;
        global $g_count_db_statements;
        global $time_total;
        global $PH;
        global $auth;

        $view= 'NORMAL';
        if(isset($auth) && $auth->cur_user && ($auth->cur_user->user_rights & RIGHT_VIEWALL)) {
            $view = 'ALL';
        }
        else if(Auth::isAnonymousUser()) {
            $view = 'GUEST';
        }

        $buffer='';

        $buffer.='<div id="footer">'
            .confGet('APP_NAME') . ' ';


        if($view != 'GUEST') {

            $buffer.=  confGet('STREBER_VERSION') . ' (' . confGet('STREBER_VERSION_DATE') . ') ';

            $TIME_END=microtime(1);
            $time_total= $TIME_END- $TIME_START;
            $time=($TIME_END-$TIME_START)*1000;
            $time_str=sprintf("%.0f",$time);
            $buffer.= " / ".__('rendered in')." $time_str ms / ";

            if(function_exists('memory_get_usage')) {
                $buffer.= __('memory used').": ".number_format(memory_get_usage(),",",".",".")." B / ";
            }

            $buffer .= ' ('. sprintf(__('%s queries / %s fields '), $g_count_db_statements, $DB_ITEMS_LOADED ) . ') ';

            if($view == 'ALL') {
                $buffer .= $PH->getLink('systemInfo','system info');
            }


            $buffer .= "<br/>";

            if(confGet('DISPLAY_ERROR_LIST') != 'NONE') {
                $buffer .= render_errors();
            }
            $buffer .= render_measures();

            if(confGet('LIST_UNDEFINED_LANG_KEYS')) {
                global $g_lang_new;
                if(isset($g_lang_new)) {
                    print "<b>undefined language keys:</b><br/>";
                    foreach($g_lang_new as $n=>$v) {
                        print "'$n'       =>'',<br/>";
                    }
                }
            }
        }

        $buffer.=  "</div>";
        return $buffer;
    }
}

/**
* render and return a table with the measured ids
*/
function render_errors() {
    global $g_error_list;
    global $auth;

    $buffer="";

    if($g_error_list) {
        $buffer.='<div class="error_list">';

        if($auth && $auth->cur_user && ($auth->cur_user->user_rights & RIGHT_VIEWALL)) {
            $str_link= "(see 'errors.log.php' for details)";
        }
        else {
            $str_link= "";
        }
        $buffer.="<em>".count($g_error_list)." errors ... $str_link</em><br/>";
        foreach($g_error_list as $e) {
            $buffer.="\n<p>".asHtml($e)."</p>";                         # 'ERROR:' will be recognized by unit-tests
        }
        $buffer.="\n</div>";
    }
    return $buffer;
}



?>