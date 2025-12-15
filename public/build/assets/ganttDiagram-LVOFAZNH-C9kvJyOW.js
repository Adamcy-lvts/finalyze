import{_ as l,g as Ae,s as Le,q as Fe,p as Ye,a as We,b as Oe,c as ut,d as Pe,av as X,l as Ct,j as Ve,i as ze,y as Ne,u as Re}from"./RichTextEditor-4qQRVwyV.js";import{ak as Rt}from"./app-DXo2qlXx.js";import{R as fe,r as Be,d as he,e as me,C as ke,n as At,h as qe,s as bt}from"./index-BWEDi_FC.js";import{t as Ge,m as He,a as Xe,b as je,c as Jt,d as te,e as Ue,f as Ze,g as $e,h as Qe,i as Ke,j as Je,k as tr,l as ee,n as re,o as ie,s as se,p as ne,q as er}from"./axis-DouJwEGg.js";import{l as rr}from"./linear-C5NI_5MW.js";import"./Progress.vue_vue_type_script_setup_true_lang-DO3MrglI.js";import"./Presence-o50VOThk.js";import"./index-CkWWMrP7.js";import"./useForwardExpose--YCaV9Ag.js";import"./index-I-wGS76U.js";import"./nullish-CHIgUVhi.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./purify.es-DxJI3Wx7.js";import"./step-DrZ9DcYd.js";import"./chart-column-DIQGbb-G.js";import"./calendar-CS_W0kI0.js";import"./brain-C5NopvCi.js";import"./users-qPvWTk-A.js";import"./circle-alert-CTxpdSQx.js";import"./eye-6CFFy5ii.js";import"./check-BWhUQiN7.js";import"./type-CK_QB0xm.js";import"./loader-circle-HypqbmCP.js";import"./download-BFKqnz8y.js";import"./trash-2-ZdRL_uST.js";import"./DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-bOf5zfcv.js";import"./index-h2yNoIu5.js";import"./x-CGUx5PeF.js";import"./DialogTitle-DmgzGPjX.js";import"./VisuallyHidden-Cpehayb9.js";import"./DialogTitle.vue_vue_type_script_setup_true_lang-Bl25YZsR.js";import"./DialogDescription.vue_vue_type_script_setup_true_lang-Dug2njhh.js";import"./DialogFooter.vue_vue_type_script_setup_true_lang-BbesDam9.js";import"./Input.vue_vue_type_script_setup_true_lang-C66Y-kcM.js";import"./Label.vue_vue_type_script_setup_true_lang-B2_Isfyb.js";import"./minus--pKQSyt-.js";import"./plus-BsOPr_x8.js";import"./list-8-t4TrUl.js";import"./init-Dmth1JHB.js";import"./defaultLocale-C4B-KCzX.js";const ir=Math.PI/180,sr=180/Math.PI,St=18,ye=.96422,pe=1,ge=.82521,ve=4/29,dt=6/29,be=3*dt*dt,nr=dt*dt*dt;function xe(t){if(t instanceof et)return new et(t.l,t.a,t.b,t.opacity);if(t instanceof it)return Te(t);t instanceof fe||(t=Be(t));var e=Wt(t.r),r=Wt(t.g),s=Wt(t.b),a=Lt((.2225045*e+.7168786*r+.0606169*s)/pe),h,f;return e===r&&r===s?h=f=a:(h=Lt((.4360747*e+.3850649*r+.1430804*s)/ye),f=Lt((.0139322*e+.0971045*r+.7141733*s)/ge)),new et(116*a-16,500*(h-a),200*(a-f),t.opacity)}function ar(t,e,r,s){return arguments.length===1?xe(t):new et(t,e,r,s??1)}function et(t,e,r,s){this.l=+t,this.a=+e,this.b=+r,this.opacity=+s}he(et,ar,me(ke,{brighter(t){return new et(this.l+St*(t??1),this.a,this.b,this.opacity)},darker(t){return new et(this.l-St*(t??1),this.a,this.b,this.opacity)},rgb(){var t=(this.l+16)/116,e=isNaN(this.a)?t:t+this.a/500,r=isNaN(this.b)?t:t-this.b/200;return e=ye*Ft(e),t=pe*Ft(t),r=ge*Ft(r),new fe(Yt(3.1338561*e-1.6168667*t-.4906146*r),Yt(-.9787684*e+1.9161415*t+.033454*r),Yt(.0719453*e-.2289914*t+1.4052427*r),this.opacity)}}));function Lt(t){return t>nr?Math.pow(t,1/3):t/be+ve}function Ft(t){return t>dt?t*t*t:be*(t-ve)}function Yt(t){return 255*(t<=.0031308?12.92*t:1.055*Math.pow(t,1/2.4)-.055)}function Wt(t){return(t/=255)<=.04045?t/12.92:Math.pow((t+.055)/1.055,2.4)}function or(t){if(t instanceof it)return new it(t.h,t.c,t.l,t.opacity);if(t instanceof et||(t=xe(t)),t.a===0&&t.b===0)return new it(NaN,0<t.l&&t.l<100?0:NaN,t.l,t.opacity);var e=Math.atan2(t.b,t.a)*sr;return new it(e<0?e+360:e,Math.sqrt(t.a*t.a+t.b*t.b),t.l,t.opacity)}function Ot(t,e,r,s){return arguments.length===1?or(t):new it(t,e,r,s??1)}function it(t,e,r,s){this.h=+t,this.c=+e,this.l=+r,this.opacity=+s}function Te(t){if(isNaN(t.h))return new et(t.l,0,0,t.opacity);var e=t.h*ir;return new et(t.l,Math.cos(e)*t.c,Math.sin(e)*t.c,t.opacity)}he(it,Ot,me(ke,{brighter(t){return new it(this.h,this.c,this.l+St*(t??1),this.opacity)},darker(t){return new it(this.h,this.c,this.l-St*(t??1),this.opacity)},rgb(){return Te(this).rgb()}}));function cr(t){return function(e,r){var s=t((e=Ot(e)).h,(r=Ot(r)).h),a=At(e.c,r.c),h=At(e.l,r.l),f=At(e.opacity,r.opacity);return function(b){return e.h=s(b),e.c=a(b),e.l=h(b),e.opacity=f(b),e+""}}}const lr=cr(qe);var xt={exports:{}},ur=xt.exports,ae;function dr(){return ae||(ae=1,(function(t,e){(function(r,s){t.exports=s()})(ur,(function(){var r="day";return function(s,a,h){var f=function(E){return E.add(4-E.isoWeekday(),r)},b=a.prototype;b.isoWeekYear=function(){return f(this).year()},b.isoWeek=function(E){if(!this.$utils().u(E))return this.add(7*(E-this.isoWeek()),r);var g,M,P,V,B=f(this),S=(g=this.isoWeekYear(),M=this.$u,P=(M?h.utc:h)().year(g).startOf("year"),V=4-P.isoWeekday(),P.isoWeekday()>4&&(V+=7),P.add(V,r));return B.diff(S,"week")+1},b.isoWeekday=function(E){return this.$utils().u(E)?this.day()||7:this.day(this.day()%7?E:E-7)};var Y=b.startOf;b.startOf=function(E,g){var M=this.$utils(),P=!!M.u(g)||g;return M.p(E)==="isoweek"?P?this.date(this.date()-(this.isoWeekday()-1)).startOf("day"):this.date(this.date()-1-(this.isoWeekday()-1)+7).endOf("day"):Y.bind(this)(E,g)}}}))})(xt)),xt.exports}var fr=dr();const hr=Rt(fr);var Tt={exports:{}},mr=Tt.exports,oe;function kr(){return oe||(oe=1,(function(t,e){(function(r,s){t.exports=s()})(mr,(function(){var r={LTS:"h:mm:ss A",LT:"h:mm A",L:"MM/DD/YYYY",LL:"MMMM D, YYYY",LLL:"MMMM D, YYYY h:mm A",LLLL:"dddd, MMMM D, YYYY h:mm A"},s=/(\[[^[]*\])|([-_:/.,()\s]+)|(A|a|Q|YYYY|YY?|ww?|MM?M?M?|Do|DD?|hh?|HH?|mm?|ss?|S{1,3}|z|ZZ?)/g,a=/\d/,h=/\d\d/,f=/\d\d?/,b=/\d*[^-_:/,()\s\d]+/,Y={},E=function(v){return(v=+v)+(v>68?1900:2e3)},g=function(v){return function(C){this[v]=+C}},M=[/[+-]\d\d:?(\d\d)?|Z/,function(v){(this.zone||(this.zone={})).offset=(function(C){if(!C||C==="Z")return 0;var L=C.match(/([+-]|\d\d)/g),F=60*L[1]+(+L[2]||0);return F===0?0:L[0]==="+"?-F:F})(v)}],P=function(v){var C=Y[v];return C&&(C.indexOf?C:C.s.concat(C.f))},V=function(v,C){var L,F=Y.meridiem;if(F){for(var G=1;G<=24;G+=1)if(v.indexOf(F(G,0,C))>-1){L=G>12;break}}else L=v===(C?"pm":"PM");return L},B={A:[b,function(v){this.afternoon=V(v,!1)}],a:[b,function(v){this.afternoon=V(v,!0)}],Q:[a,function(v){this.month=3*(v-1)+1}],S:[a,function(v){this.milliseconds=100*+v}],SS:[h,function(v){this.milliseconds=10*+v}],SSS:[/\d{3}/,function(v){this.milliseconds=+v}],s:[f,g("seconds")],ss:[f,g("seconds")],m:[f,g("minutes")],mm:[f,g("minutes")],H:[f,g("hours")],h:[f,g("hours")],HH:[f,g("hours")],hh:[f,g("hours")],D:[f,g("day")],DD:[h,g("day")],Do:[b,function(v){var C=Y.ordinal,L=v.match(/\d+/);if(this.day=L[0],C)for(var F=1;F<=31;F+=1)C(F).replace(/\[|\]/g,"")===v&&(this.day=F)}],w:[f,g("week")],ww:[h,g("week")],M:[f,g("month")],MM:[h,g("month")],MMM:[b,function(v){var C=P("months"),L=(P("monthsShort")||C.map((function(F){return F.slice(0,3)}))).indexOf(v)+1;if(L<1)throw new Error;this.month=L%12||L}],MMMM:[b,function(v){var C=P("months").indexOf(v)+1;if(C<1)throw new Error;this.month=C%12||C}],Y:[/[+-]?\d+/,g("year")],YY:[h,function(v){this.year=E(v)}],YYYY:[/\d{4}/,g("year")],Z:M,ZZ:M};function S(v){var C,L;C=v,L=Y&&Y.formats;for(var F=(v=C.replace(/(\[[^\]]+])|(LTS?|l{1,4}|L{1,4})/g,(function(w,x,p){var _=p&&p.toUpperCase();return x||L[p]||r[p]||L[_].replace(/(\[[^\]]+])|(MMMM|MM|DD|dddd)/g,(function(c,d,m){return d||m.slice(1)}))}))).match(s),G=F.length,H=0;H<G;H+=1){var $=F[H],j=B[$],y=j&&j[0],T=j&&j[1];F[H]=T?{regex:y,parser:T}:$.replace(/^\[|\]$/g,"")}return function(w){for(var x={},p=0,_=0;p<G;p+=1){var c=F[p];if(typeof c=="string")_+=c.length;else{var d=c.regex,m=c.parser,u=w.slice(_),k=d.exec(u)[0];m.call(x,k),w=w.replace(k,"")}}return(function(n){var o=n.afternoon;if(o!==void 0){var i=n.hours;o?i<12&&(n.hours+=12):i===12&&(n.hours=0),delete n.afternoon}})(x),x}}return function(v,C,L){L.p.customParseFormat=!0,v&&v.parseTwoDigitYear&&(E=v.parseTwoDigitYear);var F=C.prototype,G=F.parse;F.parse=function(H){var $=H.date,j=H.utc,y=H.args;this.$u=j;var T=y[1];if(typeof T=="string"){var w=y[2]===!0,x=y[3]===!0,p=w||x,_=y[2];x&&(_=y[2]),Y=this.$locale(),!w&&_&&(Y=L.Ls[_]),this.$d=(function(u,k,n,o){try{if(["x","X"].indexOf(k)>-1)return new Date((k==="X"?1e3:1)*u);var i=S(k)(u),I=i.year,D=i.month,A=i.day,R=i.hours,W=i.minutes,O=i.seconds,Q=i.milliseconds,ot=i.zone,ct=i.week,mt=new Date,kt=A||(I||D?1:mt.getDate()),lt=I||mt.getFullYear(),z=0;I&&!D||(z=D>0?D-1:mt.getMonth());var Z,q=R||0,nt=W||0,K=O||0,st=Q||0;return ot?new Date(Date.UTC(lt,z,kt,q,nt,K,st+60*ot.offset*1e3)):n?new Date(Date.UTC(lt,z,kt,q,nt,K,st)):(Z=new Date(lt,z,kt,q,nt,K,st),ct&&(Z=o(Z).week(ct).toDate()),Z)}catch{return new Date("")}})($,T,j,L),this.init(),_&&_!==!0&&(this.$L=this.locale(_).$L),p&&$!=this.format(T)&&(this.$d=new Date("")),Y={}}else if(T instanceof Array)for(var c=T.length,d=1;d<=c;d+=1){y[1]=T[d-1];var m=L.apply(this,y);if(m.isValid()){this.$d=m.$d,this.$L=m.$L,this.init();break}d===c&&(this.$d=new Date(""))}else G.call(this,H)}}}))})(Tt)),Tt.exports}var yr=kr();const pr=Rt(yr);var wt={exports:{}},gr=wt.exports,ce;function vr(){return ce||(ce=1,(function(t,e){(function(r,s){t.exports=s()})(gr,(function(){return function(r,s){var a=s.prototype,h=a.format;a.format=function(f){var b=this,Y=this.$locale();if(!this.isValid())return h.bind(this)(f);var E=this.$utils(),g=(f||"YYYY-MM-DDTHH:mm:ssZ").replace(/\[([^\]]+)]|Q|wo|ww|w|WW|W|zzz|z|gggg|GGGG|Do|X|x|k{1,2}|S/g,(function(M){switch(M){case"Q":return Math.ceil((b.$M+1)/3);case"Do":return Y.ordinal(b.$D);case"gggg":return b.weekYear();case"GGGG":return b.isoWeekYear();case"wo":return Y.ordinal(b.week(),"W");case"w":case"ww":return E.s(b.week(),M==="w"?1:2,"0");case"W":case"WW":return E.s(b.isoWeek(),M==="W"?1:2,"0");case"k":case"kk":return E.s(String(b.$H===0?24:b.$H),M==="k"?1:2,"0");case"X":return Math.floor(b.$d.getTime()/1e3);case"x":return b.$d.getTime();case"z":return"["+b.offsetName()+"]";case"zzz":return"["+b.offsetName("long")+"]";default:return M}}));return h.bind(this)(g)}}}))})(wt)),wt.exports}var br=vr();const xr=Rt(br);var Pt=(function(){var t=l(function(_,c,d,m){for(d=d||{},m=_.length;m--;d[_[m]]=c);return d},"o"),e=[6,8,10,12,13,14,15,16,17,18,20,21,22,23,24,25,26,27,28,29,30,31,33,35,36,38,40],r=[1,26],s=[1,27],a=[1,28],h=[1,29],f=[1,30],b=[1,31],Y=[1,32],E=[1,33],g=[1,34],M=[1,9],P=[1,10],V=[1,11],B=[1,12],S=[1,13],v=[1,14],C=[1,15],L=[1,16],F=[1,19],G=[1,20],H=[1,21],$=[1,22],j=[1,23],y=[1,25],T=[1,35],w={trace:l(function(){},"trace"),yy:{},symbols_:{error:2,start:3,gantt:4,document:5,EOF:6,line:7,SPACE:8,statement:9,NL:10,weekday:11,weekday_monday:12,weekday_tuesday:13,weekday_wednesday:14,weekday_thursday:15,weekday_friday:16,weekday_saturday:17,weekday_sunday:18,weekend:19,weekend_friday:20,weekend_saturday:21,dateFormat:22,inclusiveEndDates:23,topAxis:24,axisFormat:25,tickInterval:26,excludes:27,includes:28,todayMarker:29,title:30,acc_title:31,acc_title_value:32,acc_descr:33,acc_descr_value:34,acc_descr_multiline_value:35,section:36,clickStatement:37,taskTxt:38,taskData:39,click:40,callbackname:41,callbackargs:42,href:43,clickStatementDebug:44,$accept:0,$end:1},terminals_:{2:"error",4:"gantt",6:"EOF",8:"SPACE",10:"NL",12:"weekday_monday",13:"weekday_tuesday",14:"weekday_wednesday",15:"weekday_thursday",16:"weekday_friday",17:"weekday_saturday",18:"weekday_sunday",20:"weekend_friday",21:"weekend_saturday",22:"dateFormat",23:"inclusiveEndDates",24:"topAxis",25:"axisFormat",26:"tickInterval",27:"excludes",28:"includes",29:"todayMarker",30:"title",31:"acc_title",32:"acc_title_value",33:"acc_descr",34:"acc_descr_value",35:"acc_descr_multiline_value",36:"section",38:"taskTxt",39:"taskData",40:"click",41:"callbackname",42:"callbackargs",43:"href"},productions_:[0,[3,3],[5,0],[5,2],[7,2],[7,1],[7,1],[7,1],[11,1],[11,1],[11,1],[11,1],[11,1],[11,1],[11,1],[19,1],[19,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,1],[9,2],[9,2],[9,1],[9,1],[9,1],[9,2],[37,2],[37,3],[37,3],[37,4],[37,3],[37,4],[37,2],[44,2],[44,3],[44,3],[44,4],[44,3],[44,4],[44,2]],performAction:l(function(c,d,m,u,k,n,o){var i=n.length-1;switch(k){case 1:return n[i-1];case 2:this.$=[];break;case 3:n[i-1].push(n[i]),this.$=n[i-1];break;case 4:case 5:this.$=n[i];break;case 6:case 7:this.$=[];break;case 8:u.setWeekday("monday");break;case 9:u.setWeekday("tuesday");break;case 10:u.setWeekday("wednesday");break;case 11:u.setWeekday("thursday");break;case 12:u.setWeekday("friday");break;case 13:u.setWeekday("saturday");break;case 14:u.setWeekday("sunday");break;case 15:u.setWeekend("friday");break;case 16:u.setWeekend("saturday");break;case 17:u.setDateFormat(n[i].substr(11)),this.$=n[i].substr(11);break;case 18:u.enableInclusiveEndDates(),this.$=n[i].substr(18);break;case 19:u.TopAxis(),this.$=n[i].substr(8);break;case 20:u.setAxisFormat(n[i].substr(11)),this.$=n[i].substr(11);break;case 21:u.setTickInterval(n[i].substr(13)),this.$=n[i].substr(13);break;case 22:u.setExcludes(n[i].substr(9)),this.$=n[i].substr(9);break;case 23:u.setIncludes(n[i].substr(9)),this.$=n[i].substr(9);break;case 24:u.setTodayMarker(n[i].substr(12)),this.$=n[i].substr(12);break;case 27:u.setDiagramTitle(n[i].substr(6)),this.$=n[i].substr(6);break;case 28:this.$=n[i].trim(),u.setAccTitle(this.$);break;case 29:case 30:this.$=n[i].trim(),u.setAccDescription(this.$);break;case 31:u.addSection(n[i].substr(8)),this.$=n[i].substr(8);break;case 33:u.addTask(n[i-1],n[i]),this.$="task";break;case 34:this.$=n[i-1],u.setClickEvent(n[i-1],n[i],null);break;case 35:this.$=n[i-2],u.setClickEvent(n[i-2],n[i-1],n[i]);break;case 36:this.$=n[i-2],u.setClickEvent(n[i-2],n[i-1],null),u.setLink(n[i-2],n[i]);break;case 37:this.$=n[i-3],u.setClickEvent(n[i-3],n[i-2],n[i-1]),u.setLink(n[i-3],n[i]);break;case 38:this.$=n[i-2],u.setClickEvent(n[i-2],n[i],null),u.setLink(n[i-2],n[i-1]);break;case 39:this.$=n[i-3],u.setClickEvent(n[i-3],n[i-1],n[i]),u.setLink(n[i-3],n[i-2]);break;case 40:this.$=n[i-1],u.setLink(n[i-1],n[i]);break;case 41:case 47:this.$=n[i-1]+" "+n[i];break;case 42:case 43:case 45:this.$=n[i-2]+" "+n[i-1]+" "+n[i];break;case 44:case 46:this.$=n[i-3]+" "+n[i-2]+" "+n[i-1]+" "+n[i];break}},"anonymous"),table:[{3:1,4:[1,2]},{1:[3]},t(e,[2,2],{5:3}),{6:[1,4],7:5,8:[1,6],9:7,10:[1,8],11:17,12:r,13:s,14:a,15:h,16:f,17:b,18:Y,19:18,20:E,21:g,22:M,23:P,24:V,25:B,26:S,27:v,28:C,29:L,30:F,31:G,33:H,35:$,36:j,37:24,38:y,40:T},t(e,[2,7],{1:[2,1]}),t(e,[2,3]),{9:36,11:17,12:r,13:s,14:a,15:h,16:f,17:b,18:Y,19:18,20:E,21:g,22:M,23:P,24:V,25:B,26:S,27:v,28:C,29:L,30:F,31:G,33:H,35:$,36:j,37:24,38:y,40:T},t(e,[2,5]),t(e,[2,6]),t(e,[2,17]),t(e,[2,18]),t(e,[2,19]),t(e,[2,20]),t(e,[2,21]),t(e,[2,22]),t(e,[2,23]),t(e,[2,24]),t(e,[2,25]),t(e,[2,26]),t(e,[2,27]),{32:[1,37]},{34:[1,38]},t(e,[2,30]),t(e,[2,31]),t(e,[2,32]),{39:[1,39]},t(e,[2,8]),t(e,[2,9]),t(e,[2,10]),t(e,[2,11]),t(e,[2,12]),t(e,[2,13]),t(e,[2,14]),t(e,[2,15]),t(e,[2,16]),{41:[1,40],43:[1,41]},t(e,[2,4]),t(e,[2,28]),t(e,[2,29]),t(e,[2,33]),t(e,[2,34],{42:[1,42],43:[1,43]}),t(e,[2,40],{41:[1,44]}),t(e,[2,35],{43:[1,45]}),t(e,[2,36]),t(e,[2,38],{42:[1,46]}),t(e,[2,37]),t(e,[2,39])],defaultActions:{},parseError:l(function(c,d){if(d.recoverable)this.trace(c);else{var m=new Error(c);throw m.hash=d,m}},"parseError"),parse:l(function(c){var d=this,m=[0],u=[],k=[null],n=[],o=this.table,i="",I=0,D=0,A=2,R=1,W=n.slice.call(arguments,1),O=Object.create(this.lexer),Q={yy:{}};for(var ot in this.yy)Object.prototype.hasOwnProperty.call(this.yy,ot)&&(Q.yy[ot]=this.yy[ot]);O.setInput(c,Q.yy),Q.yy.lexer=O,Q.yy.parser=this,typeof O.yylloc>"u"&&(O.yylloc={});var ct=O.yylloc;n.push(ct);var mt=O.options&&O.options.ranges;typeof Q.yy.parseError=="function"?this.parseError=Q.yy.parseError:this.parseError=Object.getPrototypeOf(this).parseError;function kt(U){m.length=m.length-2*U,k.length=k.length-U,n.length=n.length-U}l(kt,"popStack");function lt(){var U;return U=u.pop()||O.lex()||R,typeof U!="number"&&(U instanceof Array&&(u=U,U=u.pop()),U=d.symbols_[U]||U),U}l(lt,"lex");for(var z,Z,q,nt,K={},st,J,Kt,vt;;){if(Z=m[m.length-1],this.defaultActions[Z]?q=this.defaultActions[Z]:((z===null||typeof z>"u")&&(z=lt()),q=o[Z]&&o[Z][z]),typeof q>"u"||!q.length||!q[0]){var It="";vt=[];for(st in o[Z])this.terminals_[st]&&st>A&&vt.push("'"+this.terminals_[st]+"'");O.showPosition?It="Parse error on line "+(I+1)+`:
`+O.showPosition()+`
Expecting `+vt.join(", ")+", got '"+(this.terminals_[z]||z)+"'":It="Parse error on line "+(I+1)+": Unexpected "+(z==R?"end of input":"'"+(this.terminals_[z]||z)+"'"),this.parseError(It,{text:O.match,token:this.terminals_[z]||z,line:O.yylineno,loc:ct,expected:vt})}if(q[0]instanceof Array&&q.length>1)throw new Error("Parse Error: multiple actions possible at state: "+Z+", token: "+z);switch(q[0]){case 1:m.push(z),k.push(O.yytext),n.push(O.yylloc),m.push(q[1]),z=null,D=O.yyleng,i=O.yytext,I=O.yylineno,ct=O.yylloc;break;case 2:if(J=this.productions_[q[1]][1],K.$=k[k.length-J],K._$={first_line:n[n.length-(J||1)].first_line,last_line:n[n.length-1].last_line,first_column:n[n.length-(J||1)].first_column,last_column:n[n.length-1].last_column},mt&&(K._$.range=[n[n.length-(J||1)].range[0],n[n.length-1].range[1]]),nt=this.performAction.apply(K,[i,D,I,Q.yy,q[1],k,n].concat(W)),typeof nt<"u")return nt;J&&(m=m.slice(0,-1*J*2),k=k.slice(0,-1*J),n=n.slice(0,-1*J)),m.push(this.productions_[q[1]][0]),k.push(K.$),n.push(K._$),Kt=o[m[m.length-2]][m[m.length-1]],m.push(Kt);break;case 3:return!0}}return!0},"parse")},x=(function(){var _={EOF:1,parseError:l(function(d,m){if(this.yy.parser)this.yy.parser.parseError(d,m);else throw new Error(d)},"parseError"),setInput:l(function(c,d){return this.yy=d||this.yy||{},this._input=c,this._more=this._backtrack=this.done=!1,this.yylineno=this.yyleng=0,this.yytext=this.matched=this.match="",this.conditionStack=["INITIAL"],this.yylloc={first_line:1,first_column:0,last_line:1,last_column:0},this.options.ranges&&(this.yylloc.range=[0,0]),this.offset=0,this},"setInput"),input:l(function(){var c=this._input[0];this.yytext+=c,this.yyleng++,this.offset++,this.match+=c,this.matched+=c;var d=c.match(/(?:\r\n?|\n).*/g);return d?(this.yylineno++,this.yylloc.last_line++):this.yylloc.last_column++,this.options.ranges&&this.yylloc.range[1]++,this._input=this._input.slice(1),c},"input"),unput:l(function(c){var d=c.length,m=c.split(/(?:\r\n?|\n)/g);this._input=c+this._input,this.yytext=this.yytext.substr(0,this.yytext.length-d),this.offset-=d;var u=this.match.split(/(?:\r\n?|\n)/g);this.match=this.match.substr(0,this.match.length-1),this.matched=this.matched.substr(0,this.matched.length-1),m.length-1&&(this.yylineno-=m.length-1);var k=this.yylloc.range;return this.yylloc={first_line:this.yylloc.first_line,last_line:this.yylineno+1,first_column:this.yylloc.first_column,last_column:m?(m.length===u.length?this.yylloc.first_column:0)+u[u.length-m.length].length-m[0].length:this.yylloc.first_column-d},this.options.ranges&&(this.yylloc.range=[k[0],k[0]+this.yyleng-d]),this.yyleng=this.yytext.length,this},"unput"),more:l(function(){return this._more=!0,this},"more"),reject:l(function(){if(this.options.backtrack_lexer)this._backtrack=!0;else return this.parseError("Lexical error on line "+(this.yylineno+1)+`. You can only invoke reject() in the lexer when the lexer is of the backtracking persuasion (options.backtrack_lexer = true).
`+this.showPosition(),{text:"",token:null,line:this.yylineno});return this},"reject"),less:l(function(c){this.unput(this.match.slice(c))},"less"),pastInput:l(function(){var c=this.matched.substr(0,this.matched.length-this.match.length);return(c.length>20?"...":"")+c.substr(-20).replace(/\n/g,"")},"pastInput"),upcomingInput:l(function(){var c=this.match;return c.length<20&&(c+=this._input.substr(0,20-c.length)),(c.substr(0,20)+(c.length>20?"...":"")).replace(/\n/g,"")},"upcomingInput"),showPosition:l(function(){var c=this.pastInput(),d=new Array(c.length+1).join("-");return c+this.upcomingInput()+`
`+d+"^"},"showPosition"),test_match:l(function(c,d){var m,u,k;if(this.options.backtrack_lexer&&(k={yylineno:this.yylineno,yylloc:{first_line:this.yylloc.first_line,last_line:this.last_line,first_column:this.yylloc.first_column,last_column:this.yylloc.last_column},yytext:this.yytext,match:this.match,matches:this.matches,matched:this.matched,yyleng:this.yyleng,offset:this.offset,_more:this._more,_input:this._input,yy:this.yy,conditionStack:this.conditionStack.slice(0),done:this.done},this.options.ranges&&(k.yylloc.range=this.yylloc.range.slice(0))),u=c[0].match(/(?:\r\n?|\n).*/g),u&&(this.yylineno+=u.length),this.yylloc={first_line:this.yylloc.last_line,last_line:this.yylineno+1,first_column:this.yylloc.last_column,last_column:u?u[u.length-1].length-u[u.length-1].match(/\r?\n?/)[0].length:this.yylloc.last_column+c[0].length},this.yytext+=c[0],this.match+=c[0],this.matches=c,this.yyleng=this.yytext.length,this.options.ranges&&(this.yylloc.range=[this.offset,this.offset+=this.yyleng]),this._more=!1,this._backtrack=!1,this._input=this._input.slice(c[0].length),this.matched+=c[0],m=this.performAction.call(this,this.yy,this,d,this.conditionStack[this.conditionStack.length-1]),this.done&&this._input&&(this.done=!1),m)return m;if(this._backtrack){for(var n in k)this[n]=k[n];return!1}return!1},"test_match"),next:l(function(){if(this.done)return this.EOF;this._input||(this.done=!0);var c,d,m,u;this._more||(this.yytext="",this.match="");for(var k=this._currentRules(),n=0;n<k.length;n++)if(m=this._input.match(this.rules[k[n]]),m&&(!d||m[0].length>d[0].length)){if(d=m,u=n,this.options.backtrack_lexer){if(c=this.test_match(m,k[n]),c!==!1)return c;if(this._backtrack){d=!1;continue}else return!1}else if(!this.options.flex)break}return d?(c=this.test_match(d,k[u]),c!==!1?c:!1):this._input===""?this.EOF:this.parseError("Lexical error on line "+(this.yylineno+1)+`. Unrecognized text.
`+this.showPosition(),{text:"",token:null,line:this.yylineno})},"next"),lex:l(function(){var d=this.next();return d||this.lex()},"lex"),begin:l(function(d){this.conditionStack.push(d)},"begin"),popState:l(function(){var d=this.conditionStack.length-1;return d>0?this.conditionStack.pop():this.conditionStack[0]},"popState"),_currentRules:l(function(){return this.conditionStack.length&&this.conditionStack[this.conditionStack.length-1]?this.conditions[this.conditionStack[this.conditionStack.length-1]].rules:this.conditions.INITIAL.rules},"_currentRules"),topState:l(function(d){return d=this.conditionStack.length-1-Math.abs(d||0),d>=0?this.conditionStack[d]:"INITIAL"},"topState"),pushState:l(function(d){this.begin(d)},"pushState"),stateStackSize:l(function(){return this.conditionStack.length},"stateStackSize"),options:{"case-insensitive":!0},performAction:l(function(d,m,u,k){switch(u){case 0:return this.begin("open_directive"),"open_directive";case 1:return this.begin("acc_title"),31;case 2:return this.popState(),"acc_title_value";case 3:return this.begin("acc_descr"),33;case 4:return this.popState(),"acc_descr_value";case 5:this.begin("acc_descr_multiline");break;case 6:this.popState();break;case 7:return"acc_descr_multiline_value";case 8:break;case 9:break;case 10:break;case 11:return 10;case 12:break;case 13:break;case 14:this.begin("href");break;case 15:this.popState();break;case 16:return 43;case 17:this.begin("callbackname");break;case 18:this.popState();break;case 19:this.popState(),this.begin("callbackargs");break;case 20:return 41;case 21:this.popState();break;case 22:return 42;case 23:this.begin("click");break;case 24:this.popState();break;case 25:return 40;case 26:return 4;case 27:return 22;case 28:return 23;case 29:return 24;case 30:return 25;case 31:return 26;case 32:return 28;case 33:return 27;case 34:return 29;case 35:return 12;case 36:return 13;case 37:return 14;case 38:return 15;case 39:return 16;case 40:return 17;case 41:return 18;case 42:return 20;case 43:return 21;case 44:return"date";case 45:return 30;case 46:return"accDescription";case 47:return 36;case 48:return 38;case 49:return 39;case 50:return":";case 51:return 6;case 52:return"INVALID"}},"anonymous"),rules:[/^(?:%%\{)/i,/^(?:accTitle\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*\{\s*)/i,/^(?:[\}])/i,/^(?:[^\}]*)/i,/^(?:%%(?!\{)*[^\n]*)/i,/^(?:[^\}]%%*[^\n]*)/i,/^(?:%%*[^\n]*[\n]*)/i,/^(?:[\n]+)/i,/^(?:\s+)/i,/^(?:%[^\n]*)/i,/^(?:href[\s]+["])/i,/^(?:["])/i,/^(?:[^"]*)/i,/^(?:call[\s]+)/i,/^(?:\([\s]*\))/i,/^(?:\()/i,/^(?:[^(]*)/i,/^(?:\))/i,/^(?:[^)]*)/i,/^(?:click[\s]+)/i,/^(?:[\s\n])/i,/^(?:[^\s\n]*)/i,/^(?:gantt\b)/i,/^(?:dateFormat\s[^#\n;]+)/i,/^(?:inclusiveEndDates\b)/i,/^(?:topAxis\b)/i,/^(?:axisFormat\s[^#\n;]+)/i,/^(?:tickInterval\s[^#\n;]+)/i,/^(?:includes\s[^#\n;]+)/i,/^(?:excludes\s[^#\n;]+)/i,/^(?:todayMarker\s[^\n;]+)/i,/^(?:weekday\s+monday\b)/i,/^(?:weekday\s+tuesday\b)/i,/^(?:weekday\s+wednesday\b)/i,/^(?:weekday\s+thursday\b)/i,/^(?:weekday\s+friday\b)/i,/^(?:weekday\s+saturday\b)/i,/^(?:weekday\s+sunday\b)/i,/^(?:weekend\s+friday\b)/i,/^(?:weekend\s+saturday\b)/i,/^(?:\d\d\d\d-\d\d-\d\d\b)/i,/^(?:title\s[^\n]+)/i,/^(?:accDescription\s[^#\n;]+)/i,/^(?:section\s[^\n]+)/i,/^(?:[^:\n]+)/i,/^(?::[^#\n;]+)/i,/^(?::)/i,/^(?:$)/i,/^(?:.)/i],conditions:{acc_descr_multiline:{rules:[6,7],inclusive:!1},acc_descr:{rules:[4],inclusive:!1},acc_title:{rules:[2],inclusive:!1},callbackargs:{rules:[21,22],inclusive:!1},callbackname:{rules:[18,19,20],inclusive:!1},href:{rules:[15,16],inclusive:!1},click:{rules:[24,25],inclusive:!1},INITIAL:{rules:[0,1,3,5,8,9,10,11,12,13,14,17,23,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52],inclusive:!0}}};return _})();w.lexer=x;function p(){this.yy={}}return l(p,"Parser"),p.prototype=w,w.Parser=p,new p})();Pt.parser=Pt;var Tr=Pt;X.extend(hr);X.extend(pr);X.extend(xr);var le={friday:5,saturday:6},tt="",Bt="",qt=void 0,Gt="",yt=[],pt=[],Ht=new Map,Xt=[],Et=[],ht="",jt="",we=["active","done","crit","milestone","vert"],Ut=[],gt=!1,Zt=!1,$t="sunday",Mt="saturday",Vt=0,wr=l(function(){Xt=[],Et=[],ht="",Ut=[],_t=0,Nt=void 0,Dt=void 0,N=[],tt="",Bt="",jt="",qt=void 0,Gt="",yt=[],pt=[],gt=!1,Zt=!1,Vt=0,Ht=new Map,Ne(),$t="sunday",Mt="saturday"},"clear"),_r=l(function(t){Bt=t},"setAxisFormat"),Dr=l(function(){return Bt},"getAxisFormat"),Cr=l(function(t){qt=t},"setTickInterval"),Sr=l(function(){return qt},"getTickInterval"),Er=l(function(t){Gt=t},"setTodayMarker"),Mr=l(function(){return Gt},"getTodayMarker"),Ir=l(function(t){tt=t},"setDateFormat"),Ar=l(function(){gt=!0},"enableInclusiveEndDates"),Lr=l(function(){return gt},"endDatesAreInclusive"),Fr=l(function(){Zt=!0},"enableTopAxis"),Yr=l(function(){return Zt},"topAxisEnabled"),Wr=l(function(t){jt=t},"setDisplayMode"),Or=l(function(){return jt},"getDisplayMode"),Pr=l(function(){return tt},"getDateFormat"),Vr=l(function(t){yt=t.toLowerCase().split(/[\s,]+/)},"setIncludes"),zr=l(function(){return yt},"getIncludes"),Nr=l(function(t){pt=t.toLowerCase().split(/[\s,]+/)},"setExcludes"),Rr=l(function(){return pt},"getExcludes"),Br=l(function(){return Ht},"getLinks"),qr=l(function(t){ht=t,Xt.push(t)},"addSection"),Gr=l(function(){return Xt},"getSections"),Hr=l(function(){let t=ue();const e=10;let r=0;for(;!t&&r<e;)t=ue(),r++;return Et=N,Et},"getTasks"),_e=l(function(t,e,r,s){const a=t.format(e.trim()),h=t.format("YYYY-MM-DD");return s.includes(a)||s.includes(h)?!1:r.includes("weekends")&&(t.isoWeekday()===le[Mt]||t.isoWeekday()===le[Mt]+1)||r.includes(t.format("dddd").toLowerCase())?!0:r.includes(a)||r.includes(h)},"isInvalidDate"),Xr=l(function(t){$t=t},"setWeekday"),jr=l(function(){return $t},"getWeekday"),Ur=l(function(t){Mt=t},"setWeekend"),De=l(function(t,e,r,s){if(!r.length||t.manualEndTime)return;let a;t.startTime instanceof Date?a=X(t.startTime):a=X(t.startTime,e,!0),a=a.add(1,"d");let h;t.endTime instanceof Date?h=X(t.endTime):h=X(t.endTime,e,!0);const[f,b]=Zr(a,h,e,r,s);t.endTime=f.toDate(),t.renderEndTime=b},"checkTaskDates"),Zr=l(function(t,e,r,s,a){let h=!1,f=null;for(;t<=e;)h||(f=e.toDate()),h=_e(t,r,s,a),h&&(e=e.add(1,"d")),t=t.add(1,"d");return[e,f]},"fixTaskDates"),zt=l(function(t,e,r){if(r=r.trim(),(e.trim()==="x"||e.trim()==="X")&&/^\d+$/.test(r))return new Date(Number(r));const a=/^after\s+(?<ids>[\d\w- ]+)/.exec(r);if(a!==null){let f=null;for(const Y of a.groups.ids.split(" ")){let E=at(Y);E!==void 0&&(!f||E.endTime>f.endTime)&&(f=E)}if(f)return f.endTime;const b=new Date;return b.setHours(0,0,0,0),b}let h=X(r,e.trim(),!0);if(h.isValid())return h.toDate();{Ct.debug("Invalid date:"+r),Ct.debug("With date format:"+e.trim());const f=new Date(r);if(f===void 0||isNaN(f.getTime())||f.getFullYear()<-1e4||f.getFullYear()>1e4)throw new Error("Invalid date:"+r);return f}},"getStartDate"),Ce=l(function(t){const e=/^(\d+(?:\.\d+)?)([Mdhmswy]|ms)$/.exec(t.trim());return e!==null?[Number.parseFloat(e[1]),e[2]]:[NaN,"ms"]},"parseDuration"),Se=l(function(t,e,r,s=!1){r=r.trim();const h=/^until\s+(?<ids>[\d\w- ]+)/.exec(r);if(h!==null){let g=null;for(const P of h.groups.ids.split(" ")){let V=at(P);V!==void 0&&(!g||V.startTime<g.startTime)&&(g=V)}if(g)return g.startTime;const M=new Date;return M.setHours(0,0,0,0),M}let f=X(r,e.trim(),!0);if(f.isValid())return s&&(f=f.add(1,"d")),f.toDate();let b=X(t);const[Y,E]=Ce(r);if(!Number.isNaN(Y)){const g=b.add(Y,E);g.isValid()&&(b=g)}return b.toDate()},"getEndDate"),_t=0,ft=l(function(t){return t===void 0?(_t=_t+1,"task"+_t):t},"parseId"),$r=l(function(t,e){let r;e.substr(0,1)===":"?r=e.substr(1,e.length):r=e;const s=r.split(","),a={};Qt(s,a,we);for(let f=0;f<s.length;f++)s[f]=s[f].trim();let h="";switch(s.length){case 1:a.id=ft(),a.startTime=t.endTime,h=s[0];break;case 2:a.id=ft(),a.startTime=zt(void 0,tt,s[0]),h=s[1];break;case 3:a.id=ft(s[0]),a.startTime=zt(void 0,tt,s[1]),h=s[2];break}return h&&(a.endTime=Se(a.startTime,tt,h,gt),a.manualEndTime=X(h,"YYYY-MM-DD",!0).isValid(),De(a,tt,pt,yt)),a},"compileData"),Qr=l(function(t,e){let r;e.substr(0,1)===":"?r=e.substr(1,e.length):r=e;const s=r.split(","),a={};Qt(s,a,we);for(let h=0;h<s.length;h++)s[h]=s[h].trim();switch(s.length){case 1:a.id=ft(),a.startTime={type:"prevTaskEnd",id:t},a.endTime={data:s[0]};break;case 2:a.id=ft(),a.startTime={type:"getStartDate",startData:s[0]},a.endTime={data:s[1]};break;case 3:a.id=ft(s[0]),a.startTime={type:"getStartDate",startData:s[1]},a.endTime={data:s[2]};break}return a},"parseData"),Nt,Dt,N=[],Ee={},Kr=l(function(t,e){const r={section:ht,type:ht,processed:!1,manualEndTime:!1,renderEndTime:null,raw:{data:e},task:t,classes:[]},s=Qr(Dt,e);r.raw.startTime=s.startTime,r.raw.endTime=s.endTime,r.id=s.id,r.prevTaskId=Dt,r.active=s.active,r.done=s.done,r.crit=s.crit,r.milestone=s.milestone,r.vert=s.vert,r.order=Vt,Vt++;const a=N.push(r);Dt=r.id,Ee[r.id]=a-1},"addTask"),at=l(function(t){const e=Ee[t];return N[e]},"findTaskById"),Jr=l(function(t,e){const r={section:ht,type:ht,description:t,task:t,classes:[]},s=$r(Nt,e);r.startTime=s.startTime,r.endTime=s.endTime,r.id=s.id,r.active=s.active,r.done=s.done,r.crit=s.crit,r.milestone=s.milestone,r.vert=s.vert,Nt=r,Et.push(r)},"addTaskOrg"),ue=l(function(){const t=l(function(r){const s=N[r];let a="";switch(N[r].raw.startTime.type){case"prevTaskEnd":{const h=at(s.prevTaskId);s.startTime=h.endTime;break}case"getStartDate":a=zt(void 0,tt,N[r].raw.startTime.startData),a&&(N[r].startTime=a);break}return N[r].startTime&&(N[r].endTime=Se(N[r].startTime,tt,N[r].raw.endTime.data,gt),N[r].endTime&&(N[r].processed=!0,N[r].manualEndTime=X(N[r].raw.endTime.data,"YYYY-MM-DD",!0).isValid(),De(N[r],tt,pt,yt))),N[r].processed},"compileTask");let e=!0;for(const[r,s]of N.entries())t(r),e=e&&s.processed;return e},"compileTasks"),ti=l(function(t,e){let r=e;ut().securityLevel!=="loose"&&(r=ze.sanitizeUrl(e)),t.split(",").forEach(function(s){at(s)!==void 0&&(Ie(s,()=>{window.open(r,"_self")}),Ht.set(s,r))}),Me(t,"clickable")},"setLink"),Me=l(function(t,e){t.split(",").forEach(function(r){let s=at(r);s!==void 0&&s.classes.push(e)})},"setClass"),ei=l(function(t,e,r){if(ut().securityLevel!=="loose"||e===void 0)return;let s=[];if(typeof r=="string"){s=r.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);for(let h=0;h<s.length;h++){let f=s[h].trim();f.startsWith('"')&&f.endsWith('"')&&(f=f.substr(1,f.length-2)),s[h]=f}}s.length===0&&s.push(t),at(t)!==void 0&&Ie(t,()=>{Re.runFunc(e,...s)})},"setClickFun"),Ie=l(function(t,e){Ut.push(function(){const r=document.querySelector(`[id="${t}"]`);r!==null&&r.addEventListener("click",function(){e()})},function(){const r=document.querySelector(`[id="${t}-text"]`);r!==null&&r.addEventListener("click",function(){e()})})},"pushFun"),ri=l(function(t,e,r){t.split(",").forEach(function(s){ei(s,e,r)}),Me(t,"clickable")},"setClickEvent"),ii=l(function(t){Ut.forEach(function(e){e(t)})},"bindFunctions"),si={getConfig:l(()=>ut().gantt,"getConfig"),clear:wr,setDateFormat:Ir,getDateFormat:Pr,enableInclusiveEndDates:Ar,endDatesAreInclusive:Lr,enableTopAxis:Fr,topAxisEnabled:Yr,setAxisFormat:_r,getAxisFormat:Dr,setTickInterval:Cr,getTickInterval:Sr,setTodayMarker:Er,getTodayMarker:Mr,setAccTitle:Oe,getAccTitle:We,setDiagramTitle:Ye,getDiagramTitle:Fe,setDisplayMode:Wr,getDisplayMode:Or,setAccDescription:Le,getAccDescription:Ae,addSection:qr,getSections:Gr,getTasks:Hr,addTask:Kr,findTaskById:at,addTaskOrg:Jr,setIncludes:Vr,getIncludes:zr,setExcludes:Nr,getExcludes:Rr,setClickEvent:ri,setLink:ti,getLinks:Br,bindFunctions:ii,parseDuration:Ce,isInvalidDate:_e,setWeekday:Xr,getWeekday:jr,setWeekend:Ur};function Qt(t,e,r){let s=!0;for(;s;)s=!1,r.forEach(function(a){const h="^\\s*"+a+"\\s*$",f=new RegExp(h);t[0].match(f)&&(e[a]=!0,t.shift(1),s=!0)})}l(Qt,"getTaskTags");var ni=l(function(){Ct.debug("Something is calling, setConf, remove the call")},"setConf"),de={monday:tr,tuesday:Je,wednesday:Ke,thursday:Qe,friday:$e,saturday:Ze,sunday:Ue},ai=l((t,e)=>{let r=[...t].map(()=>-1/0),s=[...t].sort((h,f)=>h.startTime-f.startTime||h.order-f.order),a=0;for(const h of s)for(let f=0;f<r.length;f++)if(h.startTime>=r[f]){r[f]=h.endTime,h.order=f+e,f>a&&(a=f);break}return a},"getMaxIntersections"),rt,oi=l(function(t,e,r,s){const a=ut().gantt,h=ut().securityLevel;let f;h==="sandbox"&&(f=bt("#i"+e));const b=h==="sandbox"?bt(f.nodes()[0].contentDocument.body):bt("body"),Y=h==="sandbox"?f.nodes()[0].contentDocument:document,E=Y.getElementById(e);rt=E.parentElement.offsetWidth,rt===void 0&&(rt=1200),a.useWidth!==void 0&&(rt=a.useWidth);const g=s.db.getTasks();let M=[];for(const y of g)M.push(y.type);M=j(M);const P={};let V=2*a.topPadding;if(s.db.getDisplayMode()==="compact"||a.displayMode==="compact"){const y={};for(const w of g)y[w.section]===void 0?y[w.section]=[w]:y[w.section].push(w);let T=0;for(const w of Object.keys(y)){const x=ai(y[w],T)+1;T+=x,V+=x*(a.barHeight+a.barGap),P[w]=x}}else{V+=g.length*(a.barHeight+a.barGap);for(const y of M)P[y]=g.filter(T=>T.type===y).length}E.setAttribute("viewBox","0 0 "+rt+" "+V);const B=b.select(`[id="${e}"]`),S=Ge().domain([He(g,function(y){return y.startTime}),Xe(g,function(y){return y.endTime})]).rangeRound([0,rt-a.leftPadding-a.rightPadding]);function v(y,T){const w=y.startTime,x=T.startTime;let p=0;return w>x?p=1:w<x&&(p=-1),p}l(v,"taskCompare"),g.sort(v),C(g,rt,V),Pe(B,V,rt,a.useMaxWidth),B.append("text").text(s.db.getDiagramTitle()).attr("x",rt/2).attr("y",a.titleTopMargin).attr("class","titleText");function C(y,T,w){const x=a.barHeight,p=x+a.barGap,_=a.topPadding,c=a.leftPadding,d=rr().domain([0,M.length]).range(["#00B9FA","#F95002"]).interpolate(lr);F(p,_,c,T,w,y,s.db.getExcludes(),s.db.getIncludes()),G(c,_,T,w),L(y,p,_,c,x,d,T),H(p,_),$(c,_,T,w)}l(C,"makeGantt");function L(y,T,w,x,p,_,c){y.sort((o,i)=>o.vert===i.vert?0:o.vert?1:-1);const m=[...new Set(y.map(o=>o.order))].map(o=>y.find(i=>i.order===o));B.append("g").selectAll("rect").data(m).enter().append("rect").attr("x",0).attr("y",function(o,i){return i=o.order,i*T+w-2}).attr("width",function(){return c-a.rightPadding/2}).attr("height",T).attr("class",function(o){for(const[i,I]of M.entries())if(o.type===I)return"section section"+i%a.numberSectionStyles;return"section section0"}).enter();const u=B.append("g").selectAll("rect").data(y).enter(),k=s.db.getLinks();if(u.append("rect").attr("id",function(o){return o.id}).attr("rx",3).attr("ry",3).attr("x",function(o){return o.milestone?S(o.startTime)+x+.5*(S(o.endTime)-S(o.startTime))-.5*p:S(o.startTime)+x}).attr("y",function(o,i){return i=o.order,o.vert?a.gridLineStartPadding:i*T+w}).attr("width",function(o){return o.milestone?p:o.vert?.08*p:S(o.renderEndTime||o.endTime)-S(o.startTime)}).attr("height",function(o){return o.vert?g.length*(a.barHeight+a.barGap)+a.barHeight*2:p}).attr("transform-origin",function(o,i){return i=o.order,(S(o.startTime)+x+.5*(S(o.endTime)-S(o.startTime))).toString()+"px "+(i*T+w+.5*p).toString()+"px"}).attr("class",function(o){const i="task";let I="";o.classes.length>0&&(I=o.classes.join(" "));let D=0;for(const[R,W]of M.entries())o.type===W&&(D=R%a.numberSectionStyles);let A="";return o.active?o.crit?A+=" activeCrit":A=" active":o.done?o.crit?A=" doneCrit":A=" done":o.crit&&(A+=" crit"),A.length===0&&(A=" task"),o.milestone&&(A=" milestone "+A),o.vert&&(A=" vert "+A),A+=D,A+=" "+I,i+A}),u.append("text").attr("id",function(o){return o.id+"-text"}).text(function(o){return o.task}).attr("font-size",a.fontSize).attr("x",function(o){let i=S(o.startTime),I=S(o.renderEndTime||o.endTime);if(o.milestone&&(i+=.5*(S(o.endTime)-S(o.startTime))-.5*p,I=i+p),o.vert)return S(o.startTime)+x;const D=this.getBBox().width;return D>I-i?I+D+1.5*a.leftPadding>c?i+x-5:I+x+5:(I-i)/2+i+x}).attr("y",function(o,i){return o.vert?a.gridLineStartPadding+g.length*(a.barHeight+a.barGap)+60:(i=o.order,i*T+a.barHeight/2+(a.fontSize/2-2)+w)}).attr("text-height",p).attr("class",function(o){const i=S(o.startTime);let I=S(o.endTime);o.milestone&&(I=i+p);const D=this.getBBox().width;let A="";o.classes.length>0&&(A=o.classes.join(" "));let R=0;for(const[O,Q]of M.entries())o.type===Q&&(R=O%a.numberSectionStyles);let W="";return o.active&&(o.crit?W="activeCritText"+R:W="activeText"+R),o.done?o.crit?W=W+" doneCritText"+R:W=W+" doneText"+R:o.crit&&(W=W+" critText"+R),o.milestone&&(W+=" milestoneText"),o.vert&&(W+=" vertText"),D>I-i?I+D+1.5*a.leftPadding>c?A+" taskTextOutsideLeft taskTextOutside"+R+" "+W:A+" taskTextOutsideRight taskTextOutside"+R+" "+W+" width-"+D:A+" taskText taskText"+R+" "+W+" width-"+D}),ut().securityLevel==="sandbox"){let o;o=bt("#i"+e);const i=o.nodes()[0].contentDocument;u.filter(function(I){return k.has(I.id)}).each(function(I){var D=i.querySelector("#"+I.id),A=i.querySelector("#"+I.id+"-text");const R=D.parentNode;var W=i.createElement("a");W.setAttribute("xlink:href",k.get(I.id)),W.setAttribute("target","_top"),R.appendChild(W),W.appendChild(D),W.appendChild(A)})}}l(L,"drawRects");function F(y,T,w,x,p,_,c,d){if(c.length===0&&d.length===0)return;let m,u;for(const{startTime:D,endTime:A}of _)(m===void 0||D<m)&&(m=D),(u===void 0||A>u)&&(u=A);if(!m||!u)return;if(X(u).diff(X(m),"year")>5){Ct.warn("The difference between the min and max time is more than 5 years. This will cause performance issues. Skipping drawing exclude days.");return}const k=s.db.getDateFormat(),n=[];let o=null,i=X(m);for(;i.valueOf()<=u;)s.db.isInvalidDate(i,k,c,d)?o?o.end=i:o={start:i,end:i}:o&&(n.push(o),o=null),i=i.add(1,"d");B.append("g").selectAll("rect").data(n).enter().append("rect").attr("id",D=>"exclude-"+D.start.format("YYYY-MM-DD")).attr("x",D=>S(D.start.startOf("day"))+w).attr("y",a.gridLineStartPadding).attr("width",D=>S(D.end.endOf("day"))-S(D.start.startOf("day"))).attr("height",p-T-a.gridLineStartPadding).attr("transform-origin",function(D,A){return(S(D.start)+w+.5*(S(D.end)-S(D.start))).toString()+"px "+(A*y+.5*p).toString()+"px"}).attr("class","exclude-range")}l(F,"drawExcludeDays");function G(y,T,w,x){const p=s.db.getDateFormat(),_=s.db.getAxisFormat();let c;_?c=_:p==="D"?c="%d":c=a.axisFormat??"%Y-%m-%d";let d=je(S).tickSize(-x+T+a.gridLineStartPadding).tickFormat(Jt(c));const u=/^([1-9]\d*)(millisecond|second|minute|hour|day|week|month)$/.exec(s.db.getTickInterval()||a.tickInterval);if(u!==null){const k=u[1],n=u[2],o=s.db.getWeekday()||a.weekday;switch(n){case"millisecond":d.ticks(ne.every(k));break;case"second":d.ticks(se.every(k));break;case"minute":d.ticks(ie.every(k));break;case"hour":d.ticks(re.every(k));break;case"day":d.ticks(ee.every(k));break;case"week":d.ticks(de[o].every(k));break;case"month":d.ticks(te.every(k));break}}if(B.append("g").attr("class","grid").attr("transform","translate("+y+", "+(x-50)+")").call(d).selectAll("text").style("text-anchor","middle").attr("fill","#000").attr("stroke","none").attr("font-size",10).attr("dy","1em"),s.db.topAxisEnabled()||a.topAxis){let k=er(S).tickSize(-x+T+a.gridLineStartPadding).tickFormat(Jt(c));if(u!==null){const n=u[1],o=u[2],i=s.db.getWeekday()||a.weekday;switch(o){case"millisecond":k.ticks(ne.every(n));break;case"second":k.ticks(se.every(n));break;case"minute":k.ticks(ie.every(n));break;case"hour":k.ticks(re.every(n));break;case"day":k.ticks(ee.every(n));break;case"week":k.ticks(de[i].every(n));break;case"month":k.ticks(te.every(n));break}}B.append("g").attr("class","grid").attr("transform","translate("+y+", "+T+")").call(k).selectAll("text").style("text-anchor","middle").attr("fill","#000").attr("stroke","none").attr("font-size",10)}}l(G,"makeGrid");function H(y,T){let w=0;const x=Object.keys(P).map(p=>[p,P[p]]);B.append("g").selectAll("text").data(x).enter().append(function(p){const _=p[0].split(Ve.lineBreakRegex),c=-(_.length-1)/2,d=Y.createElementNS("http://www.w3.org/2000/svg","text");d.setAttribute("dy",c+"em");for(const[m,u]of _.entries()){const k=Y.createElementNS("http://www.w3.org/2000/svg","tspan");k.setAttribute("alignment-baseline","central"),k.setAttribute("x","10"),m>0&&k.setAttribute("dy","1em"),k.textContent=u,d.appendChild(k)}return d}).attr("x",10).attr("y",function(p,_){if(_>0)for(let c=0;c<_;c++)return w+=x[_-1][1],p[1]*y/2+w*y+T;else return p[1]*y/2+T}).attr("font-size",a.sectionFontSize).attr("class",function(p){for(const[_,c]of M.entries())if(p[0]===c)return"sectionTitle sectionTitle"+_%a.numberSectionStyles;return"sectionTitle"})}l(H,"vertLabels");function $(y,T,w,x){const p=s.db.getTodayMarker();if(p==="off")return;const _=B.append("g").attr("class","today"),c=new Date,d=_.append("line");d.attr("x1",S(c)+y).attr("x2",S(c)+y).attr("y1",a.titleTopMargin).attr("y2",x-a.titleTopMargin).attr("class","today"),p!==""&&d.attr("style",p.replace(/,/g,";"))}l($,"drawToday");function j(y){const T={},w=[];for(let x=0,p=y.length;x<p;++x)Object.prototype.hasOwnProperty.call(T,y[x])||(T[y[x]]=!0,w.push(y[x]));return w}l(j,"checkUnique")},"draw"),ci={setConf:ni,draw:oi},li=l(t=>`
  .mermaid-main-font {
        font-family: ${t.fontFamily};
  }

  .exclude-range {
    fill: ${t.excludeBkgColor};
  }

  .section {
    stroke: none;
    opacity: 0.2;
  }

  .section0 {
    fill: ${t.sectionBkgColor};
  }

  .section2 {
    fill: ${t.sectionBkgColor2};
  }

  .section1,
  .section3 {
    fill: ${t.altSectionBkgColor};
    opacity: 0.2;
  }

  .sectionTitle0 {
    fill: ${t.titleColor};
  }

  .sectionTitle1 {
    fill: ${t.titleColor};
  }

  .sectionTitle2 {
    fill: ${t.titleColor};
  }

  .sectionTitle3 {
    fill: ${t.titleColor};
  }

  .sectionTitle {
    text-anchor: start;
    font-family: ${t.fontFamily};
  }


  /* Grid and axis */

  .grid .tick {
    stroke: ${t.gridColor};
    opacity: 0.8;
    shape-rendering: crispEdges;
  }

  .grid .tick text {
    font-family: ${t.fontFamily};
    fill: ${t.textColor};
  }

  .grid path {
    stroke-width: 0;
  }


  /* Today line */

  .today {
    fill: none;
    stroke: ${t.todayLineColor};
    stroke-width: 2px;
  }


  /* Task styling */

  /* Default task */

  .task {
    stroke-width: 2;
  }

  .taskText {
    text-anchor: middle;
    font-family: ${t.fontFamily};
  }

  .taskTextOutsideRight {
    fill: ${t.taskTextDarkColor};
    text-anchor: start;
    font-family: ${t.fontFamily};
  }

  .taskTextOutsideLeft {
    fill: ${t.taskTextDarkColor};
    text-anchor: end;
  }


  /* Special case clickable */

  .task.clickable {
    cursor: pointer;
  }

  .taskText.clickable {
    cursor: pointer;
    fill: ${t.taskTextClickableColor} !important;
    font-weight: bold;
  }

  .taskTextOutsideLeft.clickable {
    cursor: pointer;
    fill: ${t.taskTextClickableColor} !important;
    font-weight: bold;
  }

  .taskTextOutsideRight.clickable {
    cursor: pointer;
    fill: ${t.taskTextClickableColor} !important;
    font-weight: bold;
  }


  /* Specific task settings for the sections*/

  .taskText0,
  .taskText1,
  .taskText2,
  .taskText3 {
    fill: ${t.taskTextColor};
  }

  .task0,
  .task1,
  .task2,
  .task3 {
    fill: ${t.taskBkgColor};
    stroke: ${t.taskBorderColor};
  }

  .taskTextOutside0,
  .taskTextOutside2
  {
    fill: ${t.taskTextOutsideColor};
  }

  .taskTextOutside1,
  .taskTextOutside3 {
    fill: ${t.taskTextOutsideColor};
  }


  /* Active task */

  .active0,
  .active1,
  .active2,
  .active3 {
    fill: ${t.activeTaskBkgColor};
    stroke: ${t.activeTaskBorderColor};
  }

  .activeText0,
  .activeText1,
  .activeText2,
  .activeText3 {
    fill: ${t.taskTextDarkColor} !important;
  }


  /* Completed task */

  .done0,
  .done1,
  .done2,
  .done3 {
    stroke: ${t.doneTaskBorderColor};
    fill: ${t.doneTaskBkgColor};
    stroke-width: 2;
  }

  .doneText0,
  .doneText1,
  .doneText2,
  .doneText3 {
    fill: ${t.taskTextDarkColor} !important;
  }


  /* Tasks on the critical line */

  .crit0,
  .crit1,
  .crit2,
  .crit3 {
    stroke: ${t.critBorderColor};
    fill: ${t.critBkgColor};
    stroke-width: 2;
  }

  .activeCrit0,
  .activeCrit1,
  .activeCrit2,
  .activeCrit3 {
    stroke: ${t.critBorderColor};
    fill: ${t.activeTaskBkgColor};
    stroke-width: 2;
  }

  .doneCrit0,
  .doneCrit1,
  .doneCrit2,
  .doneCrit3 {
    stroke: ${t.critBorderColor};
    fill: ${t.doneTaskBkgColor};
    stroke-width: 2;
    cursor: pointer;
    shape-rendering: crispEdges;
  }

  .milestone {
    transform: rotate(45deg) scale(0.8,0.8);
  }

  .milestoneText {
    font-style: italic;
  }
  .doneCritText0,
  .doneCritText1,
  .doneCritText2,
  .doneCritText3 {
    fill: ${t.taskTextDarkColor} !important;
  }

  .vert {
    stroke: ${t.vertLineColor};
  }

  .vertText {
    font-size: 15px;
    text-anchor: middle;
    fill: ${t.vertLineColor} !important;
  }

  .activeCritText0,
  .activeCritText1,
  .activeCritText2,
  .activeCritText3 {
    fill: ${t.taskTextDarkColor} !important;
  }

  .titleText {
    text-anchor: middle;
    font-size: 18px;
    fill: ${t.titleColor||t.textColor};
    font-family: ${t.fontFamily};
  }
`,"getStyles"),ui=li,Qi={parser:Tr,db:si,renderer:ci,styles:ui};export{Qi as diagram};
