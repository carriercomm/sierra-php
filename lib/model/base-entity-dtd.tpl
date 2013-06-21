<!--
 +~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~+
 | SIERRA : PHP Application Framework  http://code.google.com/p/sierra-php |
 +~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~+
 |                   *** DO NOT EDIT THIS FILE ***                         |
 |                                                                         |
 | This dtd source file was generated by the SIERRA Entity modeler.        |
 | according to the entity model described in the source file listed below.|
 | This file will be overwritten each time a change to that file is made.  |
 +~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~+
 entity model source file: {$entityModelPath}


{$resources->getString('text.doc-type-tag')}

<!DOCTYPE {$docType} PUBLIC "-//SIERRA//DTD {$docType}//{$Controller->getAppDefaultLanguage()|upper}" 
  "{if $dtdUri}{$dtdUri}{else}file://{$dtdPath}{/if}">
-->


<!--
{$resources->getString('model.root-dtd-element')}

app 							{$resources->getString('model.root-dtd-element.app')}

app-path					{$resources->getString('model.root-dtd-element.app-path')}

sierra-path				{$resources->getString('model.root-dtd-element.sierra-path')}
-->
<!ELEMENT {$docType} ({assign var="started" value="0"}{foreach from=$entityElements item=element}{if $started}, {else}{assign var="started" value="1"}{/if}{$element}*{/foreach}{if $started}, {/if}{$viewResourcesElementName}*)>
<!ATTLIST {$docType} app CDATA #IMPLIED>
<!ATTLIST {$docType} app-path CDATA #IMPLIED>
<!ATTLIST {$docType} sierra-path CDATA #IMPLIED>

<!--
{$resources->getString('model.file.api')}

name							{$resources->getString('model.file.api.name')}

size							{$resources->getString('model.file.api.size')}

type							{$resources->getString('model.file.api.type')}

type							{$resources->getString('model.file.api.uri')}

-->
<!ELEMENT {$fileElementName} EMPTY>
<!ATTLIST {$fileElementName} name CDATA #REQUIRED>
<!ATTLIST {$fileElementName} size CDATA #REQUIRED>
<!ATTLIST {$fileElementName} type CDATA #REQUIRED>
<!ATTLIST {$fileElementName} uri  CDATA #REQUIRED>


<!--
{$resources->getString('model.view-resources.api')}

id  							{$resources->getString('model.view-resources.id.api')}

-->
<!ELEMENT {$viewResourcesElementName} ({$viewResourcesStringElementName}*)>
<!ATTLIST {$viewResourcesElementName} id CDATA #REQUIRED>


<!--
{$resources->getString('model.view-resources.string.api')}

id  							{$resources->getString('model.view-resources.string.id.api')}

-->
<!ELEMENT {$viewResourcesStringElementName} (#PCDATA)>
<!ATTLIST {$viewResourcesStringElementName} id CDATA #REQUIRED>


