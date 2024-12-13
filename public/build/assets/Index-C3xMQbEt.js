import{o as r,d as o,a as e,F as f,g as b,n as x,c as h,u as a,i as v,e as _,T as k,r as w,b as d,w as n,f as p,t as m,h as M}from"./app-_5nc08WR.js";import{_ as D}from"./AppLayout-BhAzi9id.js";import T from"./Page-f4rTncfr.js";import{D as c}from"./datetime-DcQSltNB.js";import{_ as E}from"./ConfirmationModal-D2A7E4xK.js";import{_ as $}from"./SecondaryButton-Y5a7IFBC.js";import{_ as C}from"./DangerButton-D1cSdOCH.js";import"./logo-3HeDyDTa.js";import"./Alert-DpZd2Qga.js";import"./Modal-pOqILs7I.js";const S={key:0,class:"flex mb-4 justify-center"},A={class:"flex flex-wrap mt-4"},I=["innerHTML"],y="mr-1 px-4 py-3 text-sm leading-4 border rounded",L={__name:"Paginator",props:{links:{type:Array,required:!0}},setup(u){return(g,l)=>u.links.length>3?(r(),o("div",S,[e("div",A,[(r(!0),o(f,null,b(u.links,i=>(r(),o(f,{key:g.key},[i.url===null?(r(),o("div",{key:0,class:x(["text-gray-400 cursor-not-allowed",y]),innerHTML:i.label,"aria-disabled":"true"},null,8,I)):(r(),h(a(v),{key:1,class:x(["hover:bg-gamboge-500 text-gamboge-500 dark:text-white hover:text-white focus:border-gamboge-500 focus:text-white",[y,{"bg-gamboge-500 text-gamboge-50":i.active}]]),href:i.url,innerHTML:i.label,"aria-disabled":"false","preserve-scroll":"","preserve-state":""},null,8,["class","href","innerHTML"]))],64))),128))])])):_("",!0)}},B={class:"relative overflow-x-auto shadow-md sm:rounded-lg"},H={class:"w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400"},N={class:"odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"},O={scope:"row",class:"px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"},V={class:"px-6 py-4 text-center"},F={class:"px-6 py-4 text-center"},j={class:"px-6 py-4 text-center"},q={key:0},z=["onClick"],P={class:"odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700"},R={colspan:"4",class:"text-center"},re={__name:"Index",props:{secrets:Array},setup(u){const g=k({}),l=w(null),i=()=>{g.delete(route("secrets.destroy",l.value.hash_id),{preserveScroll:!0,onFinish:()=>l.value=null})};return(G,t)=>(r(),o(f,null,[d(D,{title:"My Secrets"},{header:n(()=>t[2]||(t[2]=[e("h2",{class:"font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"}," My Secrets ",-1)])),default:n(()=>[d(T,null,{default:n(()=>[e("div",B,[e("table",H,[t[3]||(t[3]=e("thead",{class:"text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"},[e("tr",null,[e("th",{scope:"col",class:"px-6 py-3"}," Message ID "),e("th",{scope:"col",class:"px-6 py-3 text-center"}," Created At "),e("th",{scope:"col",class:"px-6 py-3 text-center"}," Expires At "),e("th",{scope:"col",class:"px-6 py-3 text-center"}," Retrieved / Burned At ")])],-1)),e("tbody",null,[(r(!0),o(f,null,b(u.secrets.data,s=>(r(),o("tr",N,[e("th",O,m(s.hash_id),1),e("td",V,m(a(c).fromISO(s.created_at).toLocaleString(a(c).DATETIME_MED)),1),e("td",F,m(a(c).fromISO(s.expires_at).toLocaleString(a(c).DATETIME_MED)),1),e("td",j,[s.retrieved_at?(r(),o("span",q,m(a(c).fromISO(s.retrieved_at).toLocaleString(a(c).DATETIME_MED)),1)):_("",!0),s.retrieved_at?_("",!0):(r(),o("button",{key:1,onClick:M(()=>l.value=s,["prevent"]),class:"inline-flex items-center font-medium text-red-600 dark:text-red-500 hover:underline cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-red-800"},"Burn",8,z))])]))),256)),e("tr",P,[e("td",R,[d(L,{links:u.secrets.meta.links},null,8,["links"])])])])])])]),_:1})]),_:1}),d(E,{show:l.value!=null,onClose:t[1]||(t[1]=s=>l.value=null)},{title:n(()=>[p(" Delete Message - "+m(l.value.hash_id),1)]),content:n(()=>t[4]||(t[4]=[p(" Are you sure you would like to burn this Message? Once burned, no one will be able to retrieve the message. ")])),footer:n(()=>[d($,{onClick:t[0]||(t[0]=s=>l.value=null)},{default:n(()=>t[5]||(t[5]=[p(" Cancel ")])),_:1}),d(C,{class:x(["ms-3",{"opacity-25":a(g).processing}]),disabled:a(g).processing,onClick:i},{default:n(()=>t[6]||(t[6]=[p(" Delete ")])),_:1},8,["class","disabled"])]),_:1},8,["show"])],64))}};export{re as default};
