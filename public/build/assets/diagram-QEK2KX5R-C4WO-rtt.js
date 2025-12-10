import{_ as l,s as k,g as R,q as F,p as I,a as _,b as E,K as D,y as G,F as f,G as C,H as P,l as z,M as H}from"./RichTextEditor-C8PJ6Ylr.js";import{p as V}from"./chunk-4BX2VUAB-DjLIeFE2.js";import{p as W}from"./treemap-KMMF4GRG-HHGj7cfS.js";import"./app-f2LOotth.js";import"./Progress.vue_vue_type_script_setup_true_lang-BL5kd6Bk.js";import"./VisuallyHidden-oDUPfO_9.js";import"./index-dTYPSduZ.js";import"./useForwardExpose-P7ffnFn1.js";import"./nullish-CHIgUVhi.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./SafeHtmlText.vue_vue_type_script_setup_true_lang-BqUqPXeU.js";import"./index-BWEDi_FC.js";import"./step-DrZ9DcYd.js";import"./chart-column-DI51Igek.js";import"./calendar-US6VzpM_.js";import"./brain-DKPRuka9.js";import"./users-DqqWjqFq.js";import"./circle-alert-3HWuB4SJ.js";import"./eye-CJK_6U1k.js";import"./check-Dut3DGyw.js";import"./download-B3xnuf0W.js";import"./trash-2-DL-ovNrC.js";import"./DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-BvX0qSLf.js";import"./DialogTitle.vue_vue_type_script_setup_true_lang-BiX3oaaL.js";import"./DialogFooter.vue_vue_type_script_setup_true_lang-2TDaNIHN.js";import"./Input.vue_vue_type_script_setup_true_lang-BnyqY6Wj.js";import"./Label.vue_vue_type_script_setup_true_lang-C4xRnpfc.js";import"./type-DGr1usN5.js";import"./minus-CWQAfKYa.js";import"./plus-C1nQ-2Wm.js";import"./list-BGXHvvgW.js";import"./_baseUniq-DhnmniV2.js";import"./_basePickBy-1H6vTeBW.js";import"./toNumber-D1r1eBOC.js";import"./clone-W4JVbJhZ.js";var h={showLegend:!0,ticks:5,max:null,min:0,graticule:"circle"},w={axes:[],curves:[],options:h},g=structuredClone(w),B=P.radar,j=l(()=>f({...B,...C().radar}),"getConfig"),b=l(()=>g.axes,"getAxes"),q=l(()=>g.curves,"getCurves"),K=l(()=>g.options,"getOptions"),N=l(a=>{g.axes=a.map(t=>({name:t.name,label:t.label??t.name}))},"setAxes"),U=l(a=>{g.curves=a.map(t=>({name:t.name,label:t.label??t.name,entries:X(t.entries)}))},"setCurves"),X=l(a=>{if(a[0].axis==null)return a.map(e=>e.value);const t=b();if(t.length===0)throw new Error("Axes must be populated before curves for reference entries");return t.map(e=>{const r=a.find(s=>s.axis?.$refText===e.name);if(r===void 0)throw new Error("Missing entry for axis "+e.label);return r.value})},"computeCurveEntries"),Y=l(a=>{const t=a.reduce((e,r)=>(e[r.name]=r,e),{});g.options={showLegend:t.showLegend?.value??h.showLegend,ticks:t.ticks?.value??h.ticks,max:t.max?.value??h.max,min:t.min?.value??h.min,graticule:t.graticule?.value??h.graticule}},"setOptions"),Z=l(()=>{G(),g=structuredClone(w)},"clear"),$={getAxes:b,getCurves:q,getOptions:K,setAxes:N,setCurves:U,setOptions:Y,getConfig:j,clear:Z,setAccTitle:E,getAccTitle:_,setDiagramTitle:I,getDiagramTitle:F,getAccDescription:R,setAccDescription:k},J=l(a=>{V(a,$);const{axes:t,curves:e,options:r}=a;$.setAxes(t),$.setCurves(e),$.setOptions(r)},"populate"),Q={parse:l(async a=>{const t=await W("radar",a);z.debug(t),J(t)},"parse")},tt=l((a,t,e,r)=>{const s=r.db,n=s.getAxes(),i=s.getCurves(),o=s.getOptions(),c=s.getConfig(),p=s.getDiagramTitle(),m=D(t),d=et(m,c),u=o.max??Math.max(...i.map(y=>Math.max(...y.entries))),x=o.min,v=Math.min(c.width,c.height)/2;at(d,n,v,o.ticks,o.graticule),rt(d,n,v,c),M(d,n,i,x,u,o.graticule,c),T(d,i,o.showLegend,c),d.append("text").attr("class","radarTitle").text(p).attr("x",0).attr("y",-c.height/2-c.marginTop)},"draw"),et=l((a,t)=>{const e=t.width+t.marginLeft+t.marginRight,r=t.height+t.marginTop+t.marginBottom,s={x:t.marginLeft+t.width/2,y:t.marginTop+t.height/2};return a.attr("viewbox",`0 0 ${e} ${r}`).attr("width",e).attr("height",r),a.append("g").attr("transform",`translate(${s.x}, ${s.y})`)},"drawFrame"),at=l((a,t,e,r,s)=>{if(s==="circle")for(let n=0;n<r;n++){const i=e*(n+1)/r;a.append("circle").attr("r",i).attr("class","radarGraticule")}else if(s==="polygon"){const n=t.length;for(let i=0;i<r;i++){const o=e*(i+1)/r,c=t.map((p,m)=>{const d=2*m*Math.PI/n-Math.PI/2,u=o*Math.cos(d),x=o*Math.sin(d);return`${u},${x}`}).join(" ");a.append("polygon").attr("points",c).attr("class","radarGraticule")}}},"drawGraticule"),rt=l((a,t,e,r)=>{const s=t.length;for(let n=0;n<s;n++){const i=t[n].label,o=2*n*Math.PI/s-Math.PI/2;a.append("line").attr("x1",0).attr("y1",0).attr("x2",e*r.axisScaleFactor*Math.cos(o)).attr("y2",e*r.axisScaleFactor*Math.sin(o)).attr("class","radarAxisLine"),a.append("text").text(i).attr("x",e*r.axisLabelFactor*Math.cos(o)).attr("y",e*r.axisLabelFactor*Math.sin(o)).attr("class","radarAxisLabel")}},"drawAxes");function M(a,t,e,r,s,n,i){const o=t.length,c=Math.min(i.width,i.height)/2;e.forEach((p,m)=>{if(p.entries.length!==o)return;const d=p.entries.map((u,x)=>{const v=2*Math.PI*x/o-Math.PI/2,y=A(u,r,s,c),O=y*Math.cos(v),S=y*Math.sin(v);return{x:O,y:S}});n==="circle"?a.append("path").attr("d",L(d,i.curveTension)).attr("class",`radarCurve-${m}`):n==="polygon"&&a.append("polygon").attr("points",d.map(u=>`${u.x},${u.y}`).join(" ")).attr("class",`radarCurve-${m}`)})}l(M,"drawCurves");function A(a,t,e,r){const s=Math.min(Math.max(a,t),e);return r*(s-t)/(e-t)}l(A,"relativeRadius");function L(a,t){const e=a.length;let r=`M${a[0].x},${a[0].y}`;for(let s=0;s<e;s++){const n=a[(s-1+e)%e],i=a[s],o=a[(s+1)%e],c=a[(s+2)%e],p={x:i.x+(o.x-n.x)*t,y:i.y+(o.y-n.y)*t},m={x:o.x-(c.x-i.x)*t,y:o.y-(c.y-i.y)*t};r+=` C${p.x},${p.y} ${m.x},${m.y} ${o.x},${o.y}`}return`${r} Z`}l(L,"closedRoundCurve");function T(a,t,e,r){if(!e)return;const s=(r.width/2+r.marginRight)*3/4,n=-(r.height/2+r.marginTop)*3/4,i=20;t.forEach((o,c)=>{const p=a.append("g").attr("transform",`translate(${s}, ${n+c*i})`);p.append("rect").attr("width",12).attr("height",12).attr("class",`radarLegendBox-${c}`),p.append("text").attr("x",16).attr("y",0).attr("class","radarLegendText").text(o.label)})}l(T,"drawLegend");var st={draw:tt},ot=l((a,t)=>{let e="";for(let r=0;r<a.THEME_COLOR_LIMIT;r++){const s=a[`cScale${r}`];e+=`
		.radarCurve-${r} {
			color: ${s};
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
			stroke-width: ${t.curveStrokeWidth};
		}
		.radarLegendBox-${r} {
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
		}
		`}return e},"genIndexStyles"),nt=l(a=>{const t=H(),e=C(),r=f(t,e.themeVariables),s=f(r.radar,a);return{themeVariables:r,radarOptions:s}},"buildRadarStyleOptions"),it=l(({radar:a}={})=>{const{themeVariables:t,radarOptions:e}=nt(a);return`
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
	${ot(t,e)}
	`},"styles"),Bt={parser:Q,db:$,renderer:st,styles:it};export{Bt as diagram};
