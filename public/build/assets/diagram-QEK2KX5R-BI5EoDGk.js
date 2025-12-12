import{_ as l,s as k,g as I,q as R,p as E,a as F,b as _,I as D,y as G,E as f,F as C,G as P,l as z,L as V}from"./RichTextEditor-Dm26X8uw.js";import{p as W}from"./chunk-4BX2VUAB-8nXqEDXP.js";import{p as B}from"./treemap-KMMF4GRG-q8zUCjyf.js";import"./app-CC2rdDfW.js";import"./Progress.vue_vue_type_script_setup_true_lang-BaFOEysi.js";import"./Presence-34c88QU_.js";import"./index-BEjGNpvr.js";import"./useForwardExpose-DNR0IPqq.js";import"./index-BMxO4AZY.js";import"./nullish-CHIgUVhi.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./SafeHtmlText.vue_vue_type_script_setup_true_lang-G6tWEomA.js";import"./index-BWEDi_FC.js";import"./step-DrZ9DcYd.js";import"./chart-column-BEeuzZr5.js";import"./calendar-CsnzqTef.js";import"./brain-CD29co4I.js";import"./users-DFe1fsDF.js";import"./circle-alert-CyMEgoP6.js";import"./eye-BNOmb8PR.js";import"./check-CBzIlgl5.js";import"./loader-circle-D8lvpJuy.js";import"./download-Dw12ld3S.js";import"./trash-2-CXXOmxpT.js";import"./DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-WxHIvWow.js";import"./VisuallyHidden-FFqQGyKV.js";import"./DialogTitle.vue_vue_type_script_setup_true_lang-Dy0-mJ6V.js";import"./x-DOb87Kyf.js";import"./DialogFooter.vue_vue_type_script_setup_true_lang-Dr-L9_LF.js";import"./Input.vue_vue_type_script_setup_true_lang-2G8HVFVW.js";import"./Label.vue_vue_type_script_setup_true_lang-lrJNxc0p.js";import"./type-BBSUUuS7.js";import"./minus-VKBe4Cy1.js";import"./plus-CqAXcHXX.js";import"./list-BgLazJlz.js";import"./_baseUniq-DOC-gjs8.js";import"./_basePickBy-pZhWTq77.js";import"./toNumber-BKFZ5vhH.js";import"./clone-B8HB4X9d.js";var h={showLegend:!0,ticks:5,max:null,min:0,graticule:"circle"},w={axes:[],curves:[],options:h},g=structuredClone(w),H=P.radar,j=l(()=>f({...H,...C().radar}),"getConfig"),b=l(()=>g.axes,"getAxes"),q=l(()=>g.curves,"getCurves"),N=l(()=>g.options,"getOptions"),U=l(r=>{g.axes=r.map(t=>({name:t.name,label:t.label??t.name}))},"setAxes"),X=l(r=>{g.curves=r.map(t=>({name:t.name,label:t.label??t.name,entries:Y(t.entries)}))},"setCurves"),Y=l(r=>{if(r[0].axis==null)return r.map(e=>e.value);const t=b();if(t.length===0)throw new Error("Axes must be populated before curves for reference entries");return t.map(e=>{const a=r.find(o=>o.axis?.$refText===e.name);if(a===void 0)throw new Error("Missing entry for axis "+e.label);return a.value})},"computeCurveEntries"),Z=l(r=>{const t=r.reduce((e,a)=>(e[a.name]=a,e),{});g.options={showLegend:t.showLegend?.value??h.showLegend,ticks:t.ticks?.value??h.ticks,max:t.max?.value??h.max,min:t.min?.value??h.min,graticule:t.graticule?.value??h.graticule}},"setOptions"),J=l(()=>{G(),g=structuredClone(w)},"clear"),$={getAxes:b,getCurves:q,getOptions:N,setAxes:U,setCurves:X,setOptions:Z,getConfig:j,clear:J,setAccTitle:_,getAccTitle:F,setDiagramTitle:E,getDiagramTitle:R,getAccDescription:I,setAccDescription:k},K=l(r=>{W(r,$);const{axes:t,curves:e,options:a}=r;$.setAxes(t),$.setCurves(e),$.setOptions(a)},"populate"),Q={parse:l(async r=>{const t=await B("radar",r);z.debug(t),K(t)},"parse")},tt=l((r,t,e,a)=>{const o=a.db,n=o.getAxes(),i=o.getCurves(),s=o.getOptions(),c=o.getConfig(),p=o.getDiagramTitle(),m=D(t),d=et(m,c),u=s.max??Math.max(...i.map(y=>Math.max(...y.entries))),x=s.min,v=Math.min(c.width,c.height)/2;rt(d,n,v,s.ticks,s.graticule),at(d,n,v,c),M(d,n,i,x,u,s.graticule,c),T(d,i,s.showLegend,c),d.append("text").attr("class","radarTitle").text(p).attr("x",0).attr("y",-c.height/2-c.marginTop)},"draw"),et=l((r,t)=>{const e=t.width+t.marginLeft+t.marginRight,a=t.height+t.marginTop+t.marginBottom,o={x:t.marginLeft+t.width/2,y:t.marginTop+t.height/2};return r.attr("viewbox",`0 0 ${e} ${a}`).attr("width",e).attr("height",a),r.append("g").attr("transform",`translate(${o.x}, ${o.y})`)},"drawFrame"),rt=l((r,t,e,a,o)=>{if(o==="circle")for(let n=0;n<a;n++){const i=e*(n+1)/a;r.append("circle").attr("r",i).attr("class","radarGraticule")}else if(o==="polygon"){const n=t.length;for(let i=0;i<a;i++){const s=e*(i+1)/a,c=t.map((p,m)=>{const d=2*m*Math.PI/n-Math.PI/2,u=s*Math.cos(d),x=s*Math.sin(d);return`${u},${x}`}).join(" ");r.append("polygon").attr("points",c).attr("class","radarGraticule")}}},"drawGraticule"),at=l((r,t,e,a)=>{const o=t.length;for(let n=0;n<o;n++){const i=t[n].label,s=2*n*Math.PI/o-Math.PI/2;r.append("line").attr("x1",0).attr("y1",0).attr("x2",e*a.axisScaleFactor*Math.cos(s)).attr("y2",e*a.axisScaleFactor*Math.sin(s)).attr("class","radarAxisLine"),r.append("text").text(i).attr("x",e*a.axisLabelFactor*Math.cos(s)).attr("y",e*a.axisLabelFactor*Math.sin(s)).attr("class","radarAxisLabel")}},"drawAxes");function M(r,t,e,a,o,n,i){const s=t.length,c=Math.min(i.width,i.height)/2;e.forEach((p,m)=>{if(p.entries.length!==s)return;const d=p.entries.map((u,x)=>{const v=2*Math.PI*x/s-Math.PI/2,y=A(u,a,o,c),O=y*Math.cos(v),S=y*Math.sin(v);return{x:O,y:S}});n==="circle"?r.append("path").attr("d",L(d,i.curveTension)).attr("class",`radarCurve-${m}`):n==="polygon"&&r.append("polygon").attr("points",d.map(u=>`${u.x},${u.y}`).join(" ")).attr("class",`radarCurve-${m}`)})}l(M,"drawCurves");function A(r,t,e,a){const o=Math.min(Math.max(r,t),e);return a*(o-t)/(e-t)}l(A,"relativeRadius");function L(r,t){const e=r.length;let a=`M${r[0].x},${r[0].y}`;for(let o=0;o<e;o++){const n=r[(o-1+e)%e],i=r[o],s=r[(o+1)%e],c=r[(o+2)%e],p={x:i.x+(s.x-n.x)*t,y:i.y+(s.y-n.y)*t},m={x:s.x-(c.x-i.x)*t,y:s.y-(c.y-i.y)*t};a+=` C${p.x},${p.y} ${m.x},${m.y} ${s.x},${s.y}`}return`${a} Z`}l(L,"closedRoundCurve");function T(r,t,e,a){if(!e)return;const o=(a.width/2+a.marginRight)*3/4,n=-(a.height/2+a.marginTop)*3/4,i=20;t.forEach((s,c)=>{const p=r.append("g").attr("transform",`translate(${o}, ${n+c*i})`);p.append("rect").attr("width",12).attr("height",12).attr("class",`radarLegendBox-${c}`),p.append("text").attr("x",16).attr("y",0).attr("class","radarLegendText").text(s.label)})}l(T,"drawLegend");var ot={draw:tt},st=l((r,t)=>{let e="";for(let a=0;a<r.THEME_COLOR_LIMIT;a++){const o=r[`cScale${a}`];e+=`
		.radarCurve-${a} {
			color: ${o};
			fill: ${o};
			fill-opacity: ${t.curveOpacity};
			stroke: ${o};
			stroke-width: ${t.curveStrokeWidth};
		}
		.radarLegendBox-${a} {
			fill: ${o};
			fill-opacity: ${t.curveOpacity};
			stroke: ${o};
		}
		`}return e},"genIndexStyles"),nt=l(r=>{const t=V(),e=C(),a=f(t,e.themeVariables),o=f(a.radar,r);return{themeVariables:a,radarOptions:o}},"buildRadarStyleOptions"),it=l(({radar:r}={})=>{const{themeVariables:t,radarOptions:e}=nt(r);return`
	.radarTitle {
		font-size: ${t.fontSize};
		color: ${t.titleColor};
		dominant-baseline: hanging;
		text-anchor: middle;
	}
	.radarAxisLine {
		stroke: ${e.axisColor};
		stroke-width: ${e.axisStrokeWidth};
	}
	.radarAxisLabel {
		dominant-baseline: middle;
		text-anchor: middle;
		font-size: ${e.axisLabelFontSize}px;
		color: ${e.axisColor};
	}
	.radarGraticule {
		fill: ${e.graticuleColor};
		fill-opacity: ${e.graticuleOpacity};
		stroke: ${e.graticuleColor};
		stroke-width: ${e.graticuleStrokeWidth};
	}
	.radarLegendText {
		text-anchor: start;
		font-size: ${e.legendFontSize}px;
		dominant-baseline: hanging;
	}
	${st(t,e)}
	`},"styles"),Ut={parser:Q,db:$,renderer:ot,styles:it};export{Ut as diagram};
