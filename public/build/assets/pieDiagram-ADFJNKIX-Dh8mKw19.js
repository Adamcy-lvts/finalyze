import{_ as u,g as j,s as q,a as Z,b as H,q as J,p as K,l as F,c as Q,E as X,I as Y,P as tt,d as et,y as rt,G as at}from"./index-CdhztUsa.js";import{p as nt}from"./chunk-4BX2VUAB-CQnc5dkW.js";import{p as it}from"./treemap-KMMF4GRG-BV_1AQv1.js";import"./index-BWEDi_FC.js";import{d as R}from"./arc-BhSQ1Fy8.js";import{o as ot}from"./ordinal-B-SEvE7q.js";import{b as S,t as z,n as st}from"./step-DrZ9DcYd.js";import"./app-Drfb5vhD.js";import"./index-Cn9MYLLD.js";import"./purify.es-DxJI3Wx7.js";import"./chart-column-CjpHg4iR.js";import"./calendar-DY5Njonr.js";import"./brain-D8XZ_dWQ.js";import"./users-BGbO8TZC.js";import"./circle-alert-D6iGcZBs.js";import"./eye-Df6YFRnF.js";import"./check-DaYZNDDf.js";import"./copy-BV7AQDwa.js";import"./loader-circle-BCOmBCcX.js";import"./download-Rmm3aUSK.js";import"./trash-2-DXyinpfS.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./_baseUniq-CHiwnTJU.js";import"./_basePickBy-B9jRSt7k.js";import"./toNumber-Qm1D3WBh.js";import"./clone-CV9KMhSp.js";import"./init-Dmth1JHB.js";function lt(t,r){return r<t?-1:r>t?1:r>=t?0:NaN}function ct(t){return t}function pt(){var t=ct,r=lt,m=null,y=S(0),o=S(z),l=S(0);function s(e){var n,c=(e=st(e)).length,d,x,h=0,p=new Array(c),i=new Array(c),v=+y.apply(this,arguments),w=Math.min(z,Math.max(-z,o.apply(this,arguments)-v)),f,C=Math.min(Math.abs(w)/c,l.apply(this,arguments)),$=C*(w<0?-1:1),g;for(n=0;n<c;++n)(g=i[p[n]=n]=+t(e[n],n,e))>0&&(h+=g);for(r!=null?p.sort(function(A,D){return r(i[A],i[D])}):m!=null&&p.sort(function(A,D){return m(e[A],e[D])}),n=0,x=h?(w-c*$)/h:0;n<c;++n,v=f)d=p[n],g=i[d],f=v+(g>0?g*x:0)+$,i[d]={data:e[d],index:n,value:g,startAngle:v,endAngle:f,padAngle:C};return i}return s.value=function(e){return arguments.length?(t=typeof e=="function"?e:S(+e),s):t},s.sortValues=function(e){return arguments.length?(r=e,m=null,s):r},s.sort=function(e){return arguments.length?(m=e,r=null,s):m},s.startAngle=function(e){return arguments.length?(y=typeof e=="function"?e:S(+e),s):y},s.endAngle=function(e){return arguments.length?(o=typeof e=="function"?e:S(+e),s):o},s.padAngle=function(e){return arguments.length?(l=typeof e=="function"?e:S(+e),s):l},s}var ut=at.pie,G={sections:new Map,showData:!1},T=G.sections,N=G.showData,dt=structuredClone(ut),gt=u(()=>structuredClone(dt),"getConfig"),mt=u(()=>{T=new Map,N=G.showData,rt()},"clear"),ft=u(({label:t,value:r})=>{if(r<0)throw new Error(`"${t}" has invalid value: ${r}. Negative values are not allowed in pie charts. All slice values must be >= 0.`);T.has(t)||(T.set(t,r),F.debug(`added new section: ${t}, with value: ${r}`))},"addSection"),ht=u(()=>T,"getSections"),vt=u(t=>{N=t},"setShowData"),St=u(()=>N,"getShowData"),L={getConfig:gt,clear:mt,setDiagramTitle:K,getDiagramTitle:J,setAccTitle:H,getAccTitle:Z,setAccDescription:q,getAccDescription:j,addSection:ft,getSections:ht,setShowData:vt,getShowData:St},yt=u((t,r)=>{nt(t,r),r.setShowData(t.showData),t.sections.map(r.addSection)},"populateDb"),xt={parse:u(async t=>{const r=await it("pie",t);F.debug(r),yt(r,L)},"parse")},wt=u(t=>`
  .pieCircle{
    stroke: ${t.pieStrokeColor};
    stroke-width : ${t.pieStrokeWidth};
    opacity : ${t.pieOpacity};
  }
  .pieOuterCircle{
    stroke: ${t.pieOuterStrokeColor};
    stroke-width: ${t.pieOuterStrokeWidth};
    fill: none;
  }
  .pieTitleText {
    text-anchor: middle;
    font-size: ${t.pieTitleTextSize};
    fill: ${t.pieTitleTextColor};
    font-family: ${t.fontFamily};
  }
  .slice {
    font-family: ${t.fontFamily};
    fill: ${t.pieSectionTextColor};
    font-size:${t.pieSectionTextSize};
    // fill: white;
  }
  .legend text {
    fill: ${t.pieLegendTextColor};
    font-family: ${t.fontFamily};
    font-size: ${t.pieLegendTextSize};
  }
`,"getStyles"),At=wt,Dt=u(t=>{const r=[...t.values()].reduce((o,l)=>o+l,0),m=[...t.entries()].map(([o,l])=>({label:o,value:l})).filter(o=>o.value/r*100>=1).sort((o,l)=>l.value-o.value);return pt().value(o=>o.value)(m)},"createPieArcs"),Ct=u((t,r,m,y)=>{F.debug(`rendering pie chart
`+t);const o=y.db,l=Q(),s=X(o.getConfig(),l.pie),e=40,n=18,c=4,d=450,x=d,h=Y(r),p=h.append("g");p.attr("transform","translate("+x/2+","+d/2+")");const{themeVariables:i}=l;let[v]=tt(i.pieOuterStrokeWidth);v??=2;const w=s.textPosition,f=Math.min(x,d)/2-e,C=R().innerRadius(0).outerRadius(f),$=R().innerRadius(f*w).outerRadius(f*w);p.append("circle").attr("cx",0).attr("cy",0).attr("r",f+v/2).attr("class","pieOuterCircle");const g=o.getSections(),A=Dt(g),D=[i.pie1,i.pie2,i.pie3,i.pie4,i.pie5,i.pie6,i.pie7,i.pie8,i.pie9,i.pie10,i.pie11,i.pie12];let b=0;g.forEach(a=>{b+=a});const P=A.filter(a=>(a.data.value/b*100).toFixed(0)!=="0"),E=ot(D);p.selectAll("mySlices").data(P).enter().append("path").attr("d",C).attr("fill",a=>E(a.data.label)).attr("class","pieCircle"),p.selectAll("mySlices").data(P).enter().append("text").text(a=>(a.data.value/b*100).toFixed(0)+"%").attr("transform",a=>"translate("+$.centroid(a)+")").style("text-anchor","middle").attr("class","slice"),p.append("text").text(o.getDiagramTitle()).attr("x",0).attr("y",-400/2).attr("class","pieTitleText");const W=[...g.entries()].map(([a,M])=>({label:a,value:M})),k=p.selectAll(".legend").data(W).enter().append("g").attr("class","legend").attr("transform",(a,M)=>{const O=n+c,B=O*W.length/2,V=12*n,U=M*O-B;return"translate("+V+","+U+")"});k.append("rect").attr("width",n).attr("height",n).style("fill",a=>E(a.label)).style("stroke",a=>E(a.label)),k.append("text").attr("x",n+c).attr("y",n-c).text(a=>o.getShowData()?`${a.label} [${a.value}]`:a.label);const _=Math.max(...k.selectAll("text").nodes().map(a=>a?.getBoundingClientRect().width??0)),I=x+e+n+c+_;h.attr("viewBox",`0 0 ${I} ${d}`),et(h,d,I,s.useMaxWidth)},"draw"),$t={draw:Ct},Yt={parser:xt,db:L,renderer:$t,styles:At};export{Yt as diagram};
