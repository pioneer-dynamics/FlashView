import{r as l,C as m,o as f,d as g,a as t,m as d,t as _,j as h,c as v,w as o,b as i,u as p,i as x,f as y}from"./app-BkqUUlrc.js";import{L as b}from"./logo-3HeDyDTa.js";import w from"./SecretForm-C0GyDNO9.js";import{_ as k}from"./AppLayout-D8e8hL66.js";import $ from"./Page-CyoMcwEo.js";import"./PrimaryButton-CeyKPZjW.js";import"./TextInput-CT4UnprT.js";import"./InputLabel-DCPkmsRQ.js";import"./datetime-DcQSltNB.js";const B={class:"w-full h-full flex justify-center items-center flex-wrap gap-2"},j={class:"text-4xl font-bold"},S={class:"text-gamboge-200 mr-2 border-r-2 animate-typing border-gamboge-200 pr-1"},N={__name:"Typewriter",props:{phrases:{type:Array,required:!0},speed:{type:Number,default:100}},setup(a){const s=a,e=l(0),n=l(""),r=l(0),c=l(!1);return m(()=>{setInterval(()=>{c.value?(n.value=s.phrases[e.value].substring(0,--r.value),r.value==0&&(c.value=!1,e.value=e.value+1,e.value==s.phrases.length&&(e.value=0))):(n.value=s.phrases[e.value].substring(0,++r.value),r.value==s.phrases[e.value].length+5&&(c.value=!0))},s.speed)}),(u,W)=>(f(),g("div",B,[t("h1",j,[d(u.$slots,"before"),t("span",S,_(n.value),1),d(u.$slots,"after")])]))}},L={class:"relative min-h-screen flex flex-col items-center justify-center"},V={class:"relative w-full max-w-2xl px-6 lg:max-w-7xl"},C={class:"grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3"},D={class:"flex lg:justify-center lg:col-start-2"},T=["src"],U={class:"mt-6 grid-cols-1 gap-6 max-w-4xl mx-auto"},F={__name:"Welcome",props:{canLogin:{type:Boolean,default:!1},canRegister:{type:Boolean,default:!1},secret:{type:String,default:null},decryptUrl:{type:String,default:null}},setup(a){return h(()=>"bg-gray-50 text-black/50 dark:bg-black dark:text-white/50 bg-cover "),(s,e)=>(f(),v(k,{title:"Welcome"},{default:o(()=>[i($,null,{default:o(()=>[t("div",L,[t("div",V,[t("header",C,[t("div",D,[i(p(x),{href:"/"},{default:o(()=>[t("img",{src:p(b),class:"h-24 w-auto"},null,8,T)]),_:1})])]),t("main",U,[i(N,{class:"text-gray-200 dark:text-white mb-6",phrases:["time-sensitive.","one-time use.","disposable."],speed:100},{before:o(()=>e[0]||(e[0]=[y(" Keep sensitive information out of your email and chat logs with links that are ")])),_:1}),i(w,{secret:a.secret,"decrypt-url":a.decryptUrl},null,8,["secret","decrypt-url"])]),e[1]||(e[1]=t("footer",{class:"py-16 text-center text-sm text-black dark:text-white/70"},null,-1))])])]),_:1})]),_:1}))}};export{F as default};
