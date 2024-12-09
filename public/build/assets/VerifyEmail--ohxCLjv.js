import{T as y,j as p,d,b as t,u as o,w as r,F as x,o as f,Z as v,a as s,e as k,n as h,f as n,i as u,h as b}from"./app-BbISSglZ.js";import{A as _}from"./AuthenticationCard-0g_S5UGa.js";import{_ as w}from"./AuthenticationCardLogo-Bo2ch0ek.js";import{_ as V}from"./PrimaryButton-Dx5i8-zr.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./logo-3HeDyDTa.js";const E={key:0,class:"mb-4 font-medium text-sm text-green-600 dark:text-green-400"},B={class:"mt-4 flex items-center justify-between"},L={__name:"VerifyEmail",props:{status:String},setup(l){const m=l,i=y({}),c=()=>{i.post(route("verification.send"))},g=p(()=>m.status==="verification-link-sent");return(a,e)=>(f(),d(x,null,[t(o(v),{title:"Email Verification"}),t(_,null,{logo:r(()=>[t(w)]),default:r(()=>[e[3]||(e[3]=s("div",{class:"mb-4 text-sm text-gray-600 dark:text-gray-400"}," Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another. ",-1)),g.value?(f(),d("div",E," A new verification link has been sent to the email address you provided in your profile settings. ")):k("",!0),s("form",{onSubmit:b(c,["prevent"])},[s("div",B,[t(V,{class:h({"opacity-25":o(i).processing}),disabled:o(i).processing},{default:r(()=>e[0]||(e[0]=[n(" Resend Verification Email ")])),_:1},8,["class","disabled"]),s("div",null,[t(o(u),{href:a.route("profile.show"),class:"underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"},{default:r(()=>e[1]||(e[1]=[n(" Edit Profile")])),_:1},8,["href"]),t(o(u),{href:a.route("logout"),method:"post",as:"button",class:"underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 ms-2"},{default:r(()=>e[2]||(e[2]=[n(" Log Out ")])),_:1},8,["href"])])])],32)]),_:1})],64))}};export{L as default};
