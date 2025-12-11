import{_ as l,s as k,g as R,q as F,p as I,a as _,b as E,K as D,y as G,F as f,G as C,H as P,l as z,M as H}from"./RichTextEditor-fWvTBqC_.js";import{p as V}from"./chunk-4BX2VUAB-D2jR8Nwi.js";import{p as W}from"./treemap-KMMF4GRG-ofVyX7n9.js";import"./app-D3VvoCeC.js";import"./Progress.vue_vue_type_script_setup_true_lang-DGoobzqN.js";import"./VisuallyHidden-7Wn15opf.js";import"./index-oxaoxNX_.js";import"./createLucideIcon-CzfKl8LH.js";import"./useForwardExpose-DaB11eBR.js";import"./nullish-CHIgUVhi.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./SafeHtmlText.vue_vue_type_script_setup_true_lang-BVowq-s1.js";import"./index-BWEDi_FC.js";import"./step-DrZ9DcYd.js";import"./chart-column-BeXZtjDv.js";import"./calendar-BBNIVdd7.js";import"./brain-BsO4gsK_.js";import"./chart-pie-Q4cuq84v.js";import"./users-BS-mwZkW.js";import"./circle-alert-Csnoof7D.js";import"./eye-Dpo5BKF4.js";import"./check-B_jYyKsN.js";import"./download-B1Aft6Bu.js";import"./trash-2-DTq965ne.js";import"./DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-BWq0DX5p.js";import"./DialogTitle.vue_vue_type_script_setup_true_lang-CJ8zVDrp.js";import"./DialogFooter.vue_vue_type_script_setup_true_lang-Dp0FW45s.js";import"./Input.vue_vue_type_script_setup_true_lang-ASpRQgPs.js";import"./Label.vue_vue_type_script_setup_true_lang-CWFc-dJT.js";import"./type-Yuk8hjvK.js";import"./minus-C9zAly3Q.js";import"./plus-Bkov1uk0.js";import"./list-BthaAsix.js";import"./_baseUniq-D2unHa9O.js";import"./_basePickBy-N5IIyEy6.js";import"./toNumber-Dp0Dcd75.js";import"./clone-Ak7_qVdJ.js";var h={showLegend:!0,ticks:5,max:null,min:0,graticule:"circle"},w={axes:[],curves:[],options:h},g=structuredClone(w),B=P.radar,j=l(()=>f({...B,...C().radar}),"getConfig"),b=l(()=>g.axes,"getAxes"),q=l(()=>g.curves,"getCurves"),K=l(()=>g.options,"getOptions"),N=l(r=>{g.axes=r.map(t=>({name:t.name,label:t.label??t.name}))},"setAxes"),U=l(r=>{g.curves=r.map(t=>({name:t.name,label:t.label??t.name,entries:X(t.entries)}))},"setCurves"),X=l(r=>{if(r[0].axis==null)return r.map(e=>e.value);const t=b();if(t.length===0)throw new Error("Axes must be populated before curves for reference entries");return t.map(e=>{const a=r.find(o=>o.axis?.$refText===e.name);if(a===void 0)throw new Error("Missing entry for axis "+e.label);return a.value})},"computeCurveEntries"),Y=l(r=>{const t=r.reduce((e,a)=>(e[a.name]=a,e),{});g.options={showLegend:t.showLegend?.value??h.showLegend,ticks:t.ticks?.value??h.ticks,max:t.max?.value??h.max,min:t.min?.value??h.min,graticule:t.graticule?.value??h.graticule}},"setOptions"),Z=l(()=>{G(),g=structuredClone(w)},"clear"),$={getAxes:b,getCurves:q,getOptions:K,setAxes:N,setCurves:U,setOptions:Y,getConfig:j,clear:Z,setAccTitle:E,getAccTitle:_,setDiagramTitle:I,getDiagramTitle:F,getAccDescription:R,setAccDescription:k},J=l(r=>{V(r,$);const{axes:t,curves:e,options:a}=r;$.setAxes(t),$.setCurves(e),$.setOptions(a)},"populate"),Q={parse:l(async r=>{const t=await W("radar",r);z.debug(t),J(t)},"parse")},tt=l((r,t,e,a)=>{const o=a.db,n=o.getAxes(),i=o.getCurves(),s=o.getOptions(),c=o.getConfig(),p=o.getDiagramTitle(),m=D(t),d=et(m,c),u=s.max??Math.max(...i.map(y=>Math.max(...y.entries))),x=s.min,v=Math.min(c.width,c.height)/2;rt(d,n,v,s.ticks,s.graticule),at(d,n,v,c),M(d,n,i,x,u,s.graticule,c),T(d,i,s.showLegend,c),d.append("text").attr("class","radarTitle").text(p).attr("x",0).attr("y",-c.height/2-c.marginTop)},"draw"),et=l((r,t)=>{const e=t.width+t.marginLeft+t.marginRight,a=t.height+t.marginTop+t.marginBottom,o={x:t.marginLeft+t.width/2,y:t.marginTop+t.height/2};return r.attr("viewbox",`0 0 ${e} ${a}`).attr("width",e).attr("height",a),r.append("g").attr("transform",`translate(${o.x}, ${o.y})`)},"drawFrame"),rt=l((r,t,e,a,o)=>{if(o==="circle")for(let n=0;n<a;n++){const i=e*(n+1)/a;r.append("circle").attr("r",i).attr("class","radarGraticule")}else if(o==="polygon"){const n=t.length;for(let i=0;i<a;i++){const s=e*(i+1)/a,c=t.map((p,m)=>{const d=2*m*Math.PI/n-Math.PI/2,u=s*Math.cos(d),x=s*Math.sin(d);return`${u},${x}`}).join(" ");r.append("polygon").attr("points",c).attr("class","radarGraticule")}}},"drawGraticule"),at=l((r,t,e,a)=>{const o=t.length;for(let n=0;n<o;n++){const i=t[n].label,s=2*n*Math.PI/o-Math.PI/2;r.append("line").attr("x1",0).attr("y1",0).attr("x2",e*a.axisScaleFactor*Math.cos(s)).attr("y2",e*a.axisScaleFactor*Math.sin(s)).attr("class","radarAxisLine"),r.append("text").text(i).attr("x",e*a.axisLabelFactor*Math.cos(s)).attr("y",e*a.axisLabelFactor*Math.sin(s)).attr("class","radarAxisLabel")}},"drawAxes");function M(r,t,e,a,o,n,i){const s=t.length,c=Math.min(i.width,i.height)/2;e.forEach((p,m)=>{if(p.entries.length!==s)return;const d=p.entries.map((u,x)=>{const v=2*Math.PI*x/s-Math.PI/2,y=A(u,a,o,c),O=y*Math.cos(v),S=y*Math.sin(v);return{x:O,y:S}});n==="circle"?r.append("path").attr("d",L(d,i.curveTension)).attr("class",`radarCurve-${m}`):n==="polygon"&&r.append("polygon").attr("points",d.map(u=>`${u.x},${u.y}`).join(" ")).attr("class",`radarCurve-${m}`)})}l(M,"drawCurves");function A(r,t,e,a){const o=Math.min(Math.max(r,t),e);return a*(o-t)/(e-t)}l(A,"relativeRadius");function L(r,t){const e=r.length;let a=`M${r[0].x},${r[0].y}`;for(let o=0;o<e;o++){const n=r[(o-1+e)%e],i=r[o],s=r[(o+1)%e],c=r[(o+2)%e],p={x:i.x+(s.x-n.x)*t,y:i.y+(s.y-n.y)*t},m={x:s.x-(c.x-i.x)*t,y:s.y-(c.y-i.y)*t};a+=` C${p.x},${p.y} ${m.x},${m.y} ${s.x},${s.y}`}return`${a} Z`}l(L,"closedRoundCurve");function T(r,t,e,a){if(!e)return;const o=(a.width/2+a.marginRight)*3/4,n=-(a.height/2+a.marginTop)*3/4,i=20;t.forEach((s,c)=>{const p=r.append("g").attr("transform",`translate(${o}, ${n+c*i})`);p.append("rect").attr("width",12).attr("height",12).attr("class",`radarLegendBox-${c}`),p.append("text").attr("x",16).attr("y",0).attr("class","radarLegendText").text(s.label)})}l(T,"drawLegend");var ot={draw:tt},st=l((r,t)=>{let e="";for(let a=0;a<r.THEME_COLOR_LIMIT;a++){const o=r[`cScale${a}`];e+=`
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
		`}return e},"genIndexStyles"),nt=l(r=>{const t=H(),e=C(),a=f(t,e.themeVariables),o=f(a.radar,r);return{themeVariables:a,radarOptions:o}},"buildRadarStyleOptions"),it=l(({radar:r}={})=>{const{themeVariables:t,radarOptions:e}=nt(r);return`
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
	`},"styles"),qt={parser:Q,db:$,renderer:ot,styles:it};export{qt as diagram};
