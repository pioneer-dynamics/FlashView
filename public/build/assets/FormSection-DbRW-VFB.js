import{k as m,o as i,e as a,b as n,w as r,r as e,a as t,i as l,n as c,f as p,G as u}from"./app-BRjscsoq.js";import{S as g}from"./SectionTitle-Dbw-805w.js";const _={class:"md:grid md:grid-cols-3"},b={class:"mt-5 md:mt-0 md:col-span-2"},f={class:"grid grid-cols-6 gap-6"},h={key:0,class:"flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-800 text-end sm:px-6 shadow sm:rounded-br-md"},S={__name:"FormSection",emits:["submitted"],setup(v){const o=m(()=>!!u().actions);return(s,d)=>(i(),a("div",_,[n(g,null,{title:r(()=>[e(s.$slots,"title")]),description:r(()=>[e(s.$slots,"description")]),_:3}),t("div",b,[t("form",{onSubmit:d[0]||(d[0]=l(y=>s.$emit("submitted"),["prevent"]))},[t("div",{class:c(["px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow",o.value?"sm:rounded-tr-md":"sm:rounded-md"])},[t("div",f,[e(s.$slots,"form")])],2),o.value?(i(),a("div",h,[e(s.$slots,"actions")])):p("",!0)],32)])]))}};export{S as _};