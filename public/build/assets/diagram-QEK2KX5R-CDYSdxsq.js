import{_ as l,s as k,g as R,q as F,p as I,a as _,b as E,K as D,y as G,F as f,G as C,H as P,l as z,M as H}from"./RichTextEditor-7Hc9mwPR.js";import{p as V}from"./chunk-4BX2VUAB-BxCLwgiz.js";import{p as W}from"./treemap-KMMF4GRG-CPWc4X4g.js";import"./app-CItGEbvq.js";import"./Progress.vue_vue_type_script_setup_true_lang-OevPul0K.js";import"./VisuallyHidden-2VQD21OR.js";import"./index-DC492BQV.js";import"./useForwardExpose-OEmEpiRH.js";import"./index-OmztQtw9.js";import"./nullish-CHIgUVhi.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./SafeHtmlText.vue_vue_type_script_setup_true_lang-CrUVDPV3.js";import"./index-BWEDi_FC.js";import"./step-DrZ9DcYd.js";import"./chart-column-Dji66vEw.js";import"./calendar-BCNzd1RS.js";import"./brain-EoSrdbMt.js";import"./users-Be1xXUye.js";import"./circle-alert-Bs9veLaG.js";import"./eye-BZhEvxuP.js";import"./check-CarLw1nQ.js";import"./download-DBAfossj.js";import"./trash-2-B_pNGVEA.js";import"./DropdownMenuTrigger.vue_vue_type_script_setup_true_lang-CKPcwZVQ.js";import"./DialogTitle.vue_vue_type_script_setup_true_lang-CobFTtce.js";import"./DialogFooter.vue_vue_type_script_setup_true_lang-DlFiywyU.js";import"./Input.vue_vue_type_script_setup_true_lang-Brg3aXbm.js";import"./Label.vue_vue_type_script_setup_true_lang-aStcuKBL.js";import"./type-DEkWV-Ea.js";import"./minus-D--F_TxH.js";import"./plus-Dw3KXGTK.js";import"./list-CAQucHZd.js";import"./_baseUniq-DhTegyKv.js";import"./_basePickBy-C0Ke7D31.js";import"./toNumber-WYyIFuef.js";import"./clone-I0RVIEWn.js";var h={showLegend:!0,ticks:5,max:null,min:0,graticule:"circle"},w={axes:[],curves:[],options:h},g=structuredClone(w),B=P.radar,j=l(()=>f({...B,...C().radar}),"getConfig"),b=l(()=>g.axes,"getAxes"),q=l(()=>g.curves,"getCurves"),K=l(()=>g.options,"getOptions"),N=l(r=>{g.axes=r.map(t=>({name:t.name,label:t.label??t.name}))},"setAxes"),U=l(r=>{g.curves=r.map(t=>({name:t.name,label:t.label??t.name,entries:X(t.entries)}))},"setCurves"),X=l(r=>{if(r[0].axis==null)return r.map(e=>e.value);const t=b();if(t.length===0)throw new Error("Axes must be populated before curves for reference entries");return t.map(e=>{const a=r.find(s=>s.axis?.$refText===e.name);if(a===void 0)throw new Error("Missing entry for axis "+e.label);return a.value})},"computeCurveEntries"),Y=l(r=>{const t=r.reduce((e,a)=>(e[a.name]=a,e),{});g.options={showLegend:t.showLegend?.value??h.showLegend,ticks:t.ticks?.value??h.ticks,max:t.max?.value??h.max,min:t.min?.value??h.min,graticule:t.graticule?.value??h.graticule}},"setOptions"),Z=l(()=>{G(),g=structuredClone(w)},"clear"),$={getAxes:b,getCurves:q,getOptions:K,setAxes:N,setCurves:U,setOptions:Y,getConfig:j,clear:Z,setAccTitle:E,getAccTitle:_,setDiagramTitle:I,getDiagramTitle:F,getAccDescription:R,setAccDescription:k},J=l(r=>{V(r,$);const{axes:t,curves:e,options:a}=r;$.setAxes(t),$.setCurves(e),$.setOptions(a)},"populate"),Q={parse:l(async r=>{const t=await W("radar",r);z.debug(t),J(t)},"parse")},tt=l((r,t,e,a)=>{const s=a.db,n=s.getAxes(),i=s.getCurves(),o=s.getOptions(),c=s.getConfig(),p=s.getDiagramTitle(),m=D(t),d=et(m,c),u=o.max??Math.max(...i.map(y=>Math.max(...y.entries))),x=o.min,v=Math.min(c.width,c.height)/2;rt(d,n,v,o.ticks,o.graticule),at(d,n,v,c),M(d,n,i,x,u,o.graticule,c),T(d,i,o.showLegend,c),d.append("text").attr("class","radarTitle").text(p).attr("x",0).attr("y",-c.height/2-c.marginTop)},"draw"),et=l((r,t)=>{const e=t.width+t.marginLeft+t.marginRight,a=t.height+t.marginTop+t.marginBottom,s={x:t.marginLeft+t.width/2,y:t.marginTop+t.height/2};return r.attr("viewbox",`0 0 ${e} ${a}`).attr("width",e).attr("height",a),r.append("g").attr("transform",`translate(${s.x}, ${s.y})`)},"drawFrame"),rt=l((r,t,e,a,s)=>{if(s==="circle")for(let n=0;n<a;n++){const i=e*(n+1)/a;r.append("circle").attr("r",i).attr("class","radarGraticule")}else if(s==="polygon"){const n=t.length;for(let i=0;i<a;i++){const o=e*(i+1)/a,c=t.map((p,m)=>{const d=2*m*Math.PI/n-Math.PI/2,u=o*Math.cos(d),x=o*Math.sin(d);return`${u},${x}`}).join(" ");r.append("polygon").attr("points",c).attr("class","radarGraticule")}}},"drawGraticule"),at=l((r,t,e,a)=>{const s=t.length;for(let n=0;n<s;n++){const i=t[n].label,o=2*n*Math.PI/s-Math.PI/2;r.append("line").attr("x1",0).attr("y1",0).attr("x2",e*a.axisScaleFactor*Math.cos(o)).attr("y2",e*a.axisScaleFactor*Math.sin(o)).attr("class","radarAxisLine"),r.append("text").text(i).attr("x",e*a.axisLabelFactor*Math.cos(o)).attr("y",e*a.axisLabelFactor*Math.sin(o)).attr("class","radarAxisLabel")}},"drawAxes");function M(r,t,e,a,s,n,i){const o=t.length,c=Math.min(i.width,i.height)/2;e.forEach((p,m)=>{if(p.entries.length!==o)return;const d=p.entries.map((u,x)=>{const v=2*Math.PI*x/o-Math.PI/2,y=A(u,a,s,c),O=y*Math.cos(v),S=y*Math.sin(v);return{x:O,y:S}});n==="circle"?r.append("path").attr("d",L(d,i.curveTension)).attr("class",`radarCurve-${m}`):n==="polygon"&&r.append("polygon").attr("points",d.map(u=>`${u.x},${u.y}`).join(" ")).attr("class",`radarCurve-${m}`)})}l(M,"drawCurves");function A(r,t,e,a){const s=Math.min(Math.max(r,t),e);return a*(s-t)/(e-t)}l(A,"relativeRadius");function L(r,t){const e=r.length;let a=`M${r[0].x},${r[0].y}`;for(let s=0;s<e;s++){const n=r[(s-1+e)%e],i=r[s],o=r[(s+1)%e],c=r[(s+2)%e],p={x:i.x+(o.x-n.x)*t,y:i.y+(o.y-n.y)*t},m={x:o.x-(c.x-i.x)*t,y:o.y-(c.y-i.y)*t};a+=` C${p.x},${p.y} ${m.x},${m.y} ${o.x},${o.y}`}return`${a} Z`}l(L,"closedRoundCurve");function T(r,t,e,a){if(!e)return;const s=(a.width/2+a.marginRight)*3/4,n=-(a.height/2+a.marginTop)*3/4,i=20;t.forEach((o,c)=>{const p=r.append("g").attr("transform",`translate(${s}, ${n+c*i})`);p.append("rect").attr("width",12).attr("height",12).attr("class",`radarLegendBox-${c}`),p.append("text").attr("x",16).attr("y",0).attr("class","radarLegendText").text(o.label)})}l(T,"drawLegend");var st={draw:tt},ot=l((r,t)=>{let e="";for(let a=0;a<r.THEME_COLOR_LIMIT;a++){const s=r[`cScale${a}`];e+=`
		.radarCurve-${a} {
			color: ${s};
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
			stroke-width: ${t.curveStrokeWidth};
		}
		.radarLegendBox-${a} {
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
		}
		`}return e},"genIndexStyles"),nt=l(r=>{const t=H(),e=C(),a=f(t,e.themeVariables),s=f(a.radar,r);return{themeVariables:a,radarOptions:s}},"buildRadarStyleOptions"),it=l(({radar:r}={})=>{const{themeVariables:t,radarOptions:e}=nt(r);return`
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
	`},"styles"),jt={parser:Q,db:$,renderer:st,styles:it};export{jt as diagram};
