(function(wp){
    'use strict';
    if(!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) return;
    var el=wp.element.createElement, register=wp.blocks.registerBlockType;
    var TextControl=wp.components.TextControl, TextareaControl=wp.components.TextareaControl, Button=wp.components.Button;
    var useBlockProps=wp.blockEditor.useBlockProps;
    function registerIfMissing(name, settings){
        if(!wp.blocks.getBlockType(name)) register(name,settings);
    }
    registerIfMissing('morvarid/resource-tip',{
        apiVersion:2,title:'نکته آموزشی',icon:'lightbulb',category:'morvarid-resource',
        description:'باکس نکته آموزشی با عنوان و توضیح قابل ویرایش.',
        attributes:{title:{type:'string',default:'نکته مهم'},text:{type:'string',default:''}},
        edit:function(props){
            return el('div',useBlockProps({className:'md-resource-tip md-resource-block-editor'}),
                el('span',{className:'md-resource-tip-icon','aria-hidden':true},'💡'),
                el('div',{},
                    el(TextControl,{label:'عنوان نکته',value:props.attributes.title,onChange:function(v){props.setAttributes({title:v});}}),
                    el(TextareaControl,{label:'توضیح نکته',value:props.attributes.text,onChange:function(v){props.setAttributes({text:v});}})
                ));
        },save:function(){return null;}
    });
    registerIfMissing('morvarid/resource-steps',{
        apiVersion:2,title:'مراحل تمرین',icon:'list-view',category:'morvarid-resource',
        description:'گام‌های قابل افزودن، حذف و ویرایش در یک باکس تمرین.',
        attributes:{title:{type:'string',default:'مراحل تمرین'},steps:{type:'array',default:['گام اول را بنویسید','گام دوم را انجام دهید']}},
        edit:function(props){
            var steps=props.attributes.steps||[];
            var children=[el(TextControl,{key:'heading',label:'عنوان تمرین',value:props.attributes.title,onChange:function(v){props.setAttributes({title:v});}})];
            steps.forEach(function(step,i){
                children.push(el('div',{className:'md-resource-step-editor',key:'step-'+i},
                    el(TextareaControl,{label:'مرحله '+(i+1),value:step,onChange:function(v){var next=steps.slice();next[i]=v;props.setAttributes({steps:next});}}),
                    el(Button,{variant:'secondary',isDestructive:true,onClick:function(){props.setAttributes({steps:steps.filter(function(_,j){return j!==i;})});}},'حذف مرحله')
                ));
            });
            children.push(el(Button,{key:'add',variant:'primary',onClick:function(){props.setAttributes({steps:steps.concat([''])});}},'+ افزودن مرحله'));
            return el('div',useBlockProps({className:'md-resource-steps md-resource-block-editor'}),children);
        },save:function(){return null;}
    });
})(window.wp);
