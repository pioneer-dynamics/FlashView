import{_ as F}from"./AppLayout-BhAzi9id.js";import E from"./Page-f4rTncfr.js";import{o as t,d as o,a,F as k,g as _,n as v,t as n,r as N,Q as A,c,w as b,b as B,f,e as p,u as m,i as y}from"./app-_5nc08WR.js";import{D}from"./datetime-DcQSltNB.js";import I from"./Feature-BYC6jb94.js";import"./logo-3HeDyDTa.js";import"./Alert-DpZd2Qga.js";const L={class:"flex flex-row mb-4"},q={class:"flex flex-row gap-0 rounded-md bg-white"},M=["onClick"],U={__name:"ToggleButton",props:{modelValue:String,options:Array},emits:["update:modelValue"],setup(h){return(i,x)=>(t(),o("div",L,[a("div",q,[(t(!0),o(k,null,_(h.options,d=>(t(),o("button",{onClick:()=>i.$emit("update:modelValue",d.value),class:v([{"bg-gamboge-300 dark:bg-gamboge-800":d.value==h.modelValue},"inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-gamboge-800 dark:text-gamboge-200 uppercase tracking-widest focus:outline-none disabled:opacity-50 transition ease-in-out duration-150 justify-center p-2"])},n(d.label),11,M))),256))])]))}},z={class:"flex flex-col md:flex-row gap-4 justify-center p-4"},O={class:"flex flex-wrap gap-2"},Q={class:"mb-4 text-xl font-medium text-gray-500 dark:text-gray-400"},R={key:0,class:"ml-2 bg-purple-100 text-purple-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded-full dark:bg-purple-900 dark:text-purple-300"},Y={key:0,class:"mb-4 text-xl font-medium text-xs text-red-500 dark:text-red-400"},G={class:"flex justify-between"},H={class:"flex items-baseline text-gray-900 dark:text-white"},J={class:"text-5xl font-extrabold tracking-tight"},K={class:"ms-1 text-xl font-normal text-gray-500 dark:text-gray-400"},W={key:0,class:"line-through decoration-gray-500 flex items-baseline text-gray-500"},X={class:"text-3xl font-extrabold tracking-tight"},Z={role:"list",class:"space-y-5 my-7"},ee={key:0},te={key:1},re={key:0},se={class:"flex flex-wrap gap-2 justify-center"},oe={key:1},ae={key:0,class:"opacity-25 inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"},ie=["href"],fe={__name:"Index",props:{plans:Array},setup(h){var w,$;const i=N((($=(w=A().props.auth)==null?void 0:w.user)==null?void 0:$.frequency)||"monthly"),x=s=>{var r,g,u,l;let e=(r=A().props.auth)==null?void 0:r.user;return((g=e==null?void 0:e.plan)==null?void 0:g.id)==s.id?i.value=="monthly"?((u=e==null?void 0:e.subscription)==null?void 0:u.stripe_price)==s.stripe_monthly_price_id:((l=e==null?void 0:e.subscription)==null?void 0:l.stripe_price)==s.stripe_yearly_price_id:!1},d=s=>s.price_per_month==0;return(s,e)=>(t(),c(F,{title:"Pricing"},{default:b(()=>[B(E,null,{default:b(()=>[B(U,{class:"justify-center",options:[{label:"Monthly",value:"monthly"},{label:"Yearly",value:"yearly"}],modelValue:i.value,"onUpdate:modelValue":e[0]||(e[0]=r=>i.value=r)},null,8,["modelValue"]),a("div",z,[(t(!0),o(k,null,_(h.plans.data,r=>{var g,u,l,j,V,C,S,P;return t(),o("div",{key:r.id,class:"w-full max-w-sm p-4 bg-gray-50 border border-gray-200 rounded-lg shadow sm:p-8 dark:bg-gray-800 dark:border-gray-700"},[a("div",O,[a("h5",Q,[f(n(r.name)+" "+n(i.value)+" ",1),i.value=="yearly"&&r.price_per_month>0?(t(),o("span",R," Save "+n(((r.price_per_month*12-r.price_per_year)/(r.price_per_month*12)*100).toFixed(2))+"% ",1)):p("",!0)]),x(r)&&((j=(l=(u=(g=s.$page.props)==null?void 0:g.auth)==null?void 0:u.user)==null?void 0:l.subscription)!=null&&j.ends_at)?(t(),o("div",Y," Expires on: "+n(m(D).fromISO((P=(S=(C=(V=s.$page.props)==null?void 0:V.auth)==null?void 0:C.user)==null?void 0:S.subscription)==null?void 0:P.ends_at).toLocaleString(m(D).DATEMED)),1)):p("",!0)]),a("div",G,[a("div",H,[e[2]||(e[2]=a("span",{class:"text-3xl font-semibold"},"A$",-1)),a("span",J,n(i.value=="monthly"?r.price_per_month:r.price_per_year),1),a("span",K,[e[1]||(e[1]=f("/ ")),a("span",null,n(i.value=="monthly"?"month":"year"),1)])]),i.value=="yearly"?(t(),o("div",W,[e[3]||(e[3]=a("span",{class:"text-3xl font-semibold"},"A$",-1)),a("span",X,n(r.price_per_month*12),1)])):p("",!0)]),a("ul",Z,[(t(!0),o(k,null,_(r.features,T=>(t(),c(I,{key:T,feature:T},null,8,["feature"]))),128))]),d(r)?(t(),o("span",ee,[s.$page.props.auth.user?p("",!0):(t(),c(m(y),{key:0,href:s.route("register"),class:"inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"},{default:b(()=>e[4]||(e[4]=[f(" Sign Up ")])),_:1},8,["href"]))])):(t(),o("span",te,[x(r)?(t(),o("span",re,[a("span",se,[s.$page.props.auth.user.subscription.ends_at?(t(),c(m(y),{key:0,method:"post",href:s.route("plans.resume"),class:"inline-flex w-full items-center px-4 py-2 bg-green-800 dark:bg-green-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-green-800 uppercase tracking-widest hover:bg-green-700 dark:hover:bg-white focus:bg-green-700 dark:focus:bg-white active:bg-green-900 dark:active:bg-green-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-green-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"},{default:b(()=>e[5]||(e[5]=[f(" Resume Plan ")])),_:1},8,["href"])):(t(),o("span",{key:1,class:v(["opacity-25 inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center",{"w-full":s.$page.props.auth.user.subscription.ends_at}])}," Current Plan ",2)),s.$page.props.auth.user.subscription.ends_at?p("",!0):(t(),c(m(y),{key:2,method:"post",href:s.route("plans.unsubscribe"),class:v([{"w-full":s.$page.props.auth.user.subscription.ends_at},"inline-flex items-center px-4 py-2 bg-red-800 dark:bg-red-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-red-800 uppercase tracking-widest hover:bg-red-700 dark:hover:bg-white focus:bg-red-700 dark:focus:bg-white active:bg-red-900 dark:active:bg-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-red-800 disabled:opacity-50 transition ease-in-out duration-150 justify-center"])},{default:b(()=>e[6]||(e[6]=[f(" Cancel Plan ")])),_:1},8,["href","class"]))])])):(t(),o("span",oe,[s.$page.props.auth.user?(t(),o("a",{key:1,href:s.route("plans.subscribe",{plan:r.id,period:i.value}),class:"inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150 w-full justify-center"}," Choose This Plan ",8,ie)):(t(),o("span",ae," Login to Subscribe "))]))]))])}),128))])]),_:1})]),_:1}))}};export{fe as default};
