import fs from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";
import { Presentation, PresentationFile } from "@oai/artifact-tool";

// The XAMPP project path is a junction to this physical directory. The
// presentation finalizer requires the non-junction workspace path.
const workspaceDir = "C:\\SantaBeachClub-BookingSystem";
const SKILL_DIR = "C:\\Users\\Justi\\.codex\\plugins\\cache\\openai-primary-runtime\\presentations\\26.905.11957\\skills\\presentations";
const TMP_DIR = path.join(workspaceDir, ".codex-deck-build");
const FINAL_PPTX = path.join(workspaceDir, "output", "Santa_Fe_Beach_Club_Project_Presentation_Expanded.pptx");
const PYTHON = "C:\\Users\\Justi\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe";
process.env.RUNTIME_NODE_MODULES = "C:\\Users\\Justi\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\node\\node_modules";
const mediaDir = path.join(workspaceDir, ".codex-deck-source", "unzipped", "word", "media");
const sourceDoc = "C:\\Words Project\\SANTA FE BEACH CLUB.docx";
const { resolvePresentationFont, finalizePresentation } = await import(pathToFileURL(path.join(SKILL_DIR, "container_tools/artifact_tool_utils.mjs")).href);
await fs.mkdir(TMP_DIR, { recursive: true });
await fs.mkdir(path.dirname(FINAL_PPTX), { recursive: true });
const font = resolvePresentationFont({ fontFamily: "Aptos", availableFonts: ["Aptos", "Arial"] });
const p = Presentation.create({ slideSize: { width: 1280, height: 720 } });

const C = { navy:"#123044", blue:"#16697A", aqua:"#82C0CC", sand:"#F3E9D2", coral:"#E86A5A", ink:"#172A3A", gray:"#5D6B75", pale:"#F7FAFA", white:"#FFFFFF" };
const note = "Source: SANTA FE BEACH CLUB.docx supplied by the user.";
function box(slide, x,y,w,h, fill=C.white, line="none", radius="roundRect") {
  return slide.shapes.add({ geometry: radius, position:{left:x,top:y,width:w,height:h}, fill, line: line==="none"?{fill:"none",width:0}:{fill:line,width:2} });
}
function text(slide, value, x,y,w,h, size=22, color=C.ink, bold=false, align="left") {
  const s=slide.shapes.add({geometry:"textbox",position:{left:x,top:y,width:w,height:h},fill:"none",line:{fill:"none",width:0}});
  s.text=value; s.text.style={typeface:font,fontSize:size,color,bold,alignment:align,verticalAlignment:"middle",autoFit:"shrinkText",wrap:true}; return s;
}
function base(title, section="SANTA FE BEACH CLUB") {
  const s=p.slides.add(); s.background.fill=C.pale;
  box(s,0,0,18,720,C.coral,"none","rect");
  text(s,section,52,24,520,26,13,C.blue,true);
  text(s,title,52,56,1175,62,34,C.navy,true);
  box(s,52,126,1176,2,C.aqua,"none","rect");
  s.speakerNotes.textFrame.setText(note); return s;
}
function bullets(slide, items, x,y,w,h, size=22, color=C.ink) {
  const val=items.map(v=>`• ${v}`).join("\n"); return text(slide,val,x,y,w,h,size,color,false);
}
function footer(slide){ text(slide,String(p.slides.items.length).padStart(2,"0"),1160,675,55,22,12,C.gray,true,"right"); }

// 1 Cover
{
 const s=p.slides.add(); s.background.fill=C.navy;
 box(s,0,520,1280,200,C.sand,"none","rect"); box(s,0,500,1280,20,C.aqua,"none","rect");
 text(s,"SANTA FE\nBEACH CLUB",70,100,820,190,52,C.white,true);
 text(s,"Web-Based Booking and Reservation Management System",74,305,900,60,25,C.aqua,false);
 text(s,"Project overview, requirements, architecture and operational flows",74,555,850,45,21,C.navy,true);
 text(s,"Justine L. Batuhan  |  Project Developer",74,615,850,32,17,C.gray,false);
 s.speakerNotes.textFrame.setText(note);
}
// 2 Introduction and background
{
 const s=base("Introduction and background");
 text(s,"Tourism and hospitality increasingly rely on online services that give guests immediate access to resort information and room availability.",70,150,1110,78,27,C.navy,true);
 box(s,70,265,520,285,C.white,C.aqua); text(s,"Current reservation setting",100,290,450,40,22,C.coral,true);
 bullets(s,["Manual processes can delay confirmations and create duplicate records","Separate booking, payment and guest files make operations harder to coordinate","Guests expect convenient online booking and timely updates"],100,345,445,165,19);
 box(s,650,265,520,285,C.sand,"none"); text(s,"System response",680,290,450,40,22,C.blue,true);
 bullets(s,["Guests can browse rooms, check availability and reserve online","Staff manage bookings, payments and schedules in one database","Centralized records reduce manual work and support better service"],680,345,445,165,19);
 footer(s);
}
// 3 Context
{
 const s=base("Project context");
 text(s,"Tourism customers increasingly expect immediate access to resort information, availability and booking confirmation.",70,155,1110,80,28,C.navy,true);
 box(s,70,265,520,300,C.white,C.aqua); text(s,"Current challenge",100,290,450,40,23,C.coral,true);
 bullets(s,["Manual reservations can create delays and duplicate bookings","Fragmented records make payments and schedules harder to manage","Guests lack a convenient self-service booking channel"],100,345,445,185,20);
 box(s,650,265,520,300,C.sand,"none"); text(s,"Proposed response",680,290,450,40,23,C.blue,true);
 bullets(s,["A web-based reservation experience with real-time availability","Centralized booking, guest, payment and operational records","Secure tools for resort staff, administrators and guests"],680,345,445,185,20); footer(s,2);
}
// 3 Problem
{
 const s=base("Statement of the problem");
 text(s,"How can a web-based system modernize reservations, improve resort operations and strengthen customer service?",80,150,1080,90,28,C.navy,true,"center");
 const items=["Online booking and customer convenience","Real-time room inventory","Centralized guest and transaction records","Front-desk workflows","Automated reports and analytics","Authentication and data protection"];
 items.forEach((v,i)=>{const col=i%3,row=Math.floor(i/3); box(s,75+col*385,285+row*125,345,88,i<3?C.white:C.sand,i<3?C.aqua:"none"); text(s,v,95+col*385,300+row*125,305,58,19,C.ink,true,"center");}); footer(s,3);
}
// 4 Objectives
{
 const s=base("Study objectives");
 text(s,"General objective",70,150,240,35,18,C.coral,true); text(s,"Design and develop a web-based booking and reservation management system for Santa Fe Beach Club.",70,190,1120,62,27,C.navy,true);
 bullets(s,["Show resort information, room types and facilities","Check room and facility availability in real time","Create, update or cancel reservations online","Manage guest, booking and payment records","Generate booking, payment and reservation reports","Send confirmations and booking notifications","Manage room schedules and availability","Protect data with authentication and access control"],90,285,1100,330,21); footer(s,4);
}
// 5 Scope
{
 const s=base("Scope of the system");
 const cols=[
  ["Guest experience","Browse rooms and availability","Online and walk-in booking","Booking access with email and OTP","QR-supported check-in","Cancellations and refund requests"],
  ["Payments and pricing","PayMongo online payments","GCash and bank-deposit records","Payment verification and balances","Promotional codes","Senior Citizen and PWD discounts"],
  ["Operations and control","Rooms, schedules and maintenance","Check-in, check-out and no-shows","Dashboards, reports and analytics","Role-based access and security logs","Backups and Progressive Web App support"]
 ];
 cols.forEach((arr,i)=>{const x=55+i*405; text(s,arr[0],x,160,360,35,21,[C.coral,C.blue,C.navy][i],true); bullets(s,arr.slice(1),x,210,350,390,19);}); footer(s,5);
}
// 6 limitations
{
 const s=base("Study limitations");
 const left=["Designed for one resort branch","No dedicated native Android or iOS app","No Agoda, Airbnb, Booking.com or Expedia integration","No automated SMS notifications","No AI recommendations, chatbot or dynamic pricing","No GPS, transport scheduling or navigation"];
 const right=["Live transactions require stable internet","Manual payments require staff verification","PayMongo availability affects online payments","Refunds depend on resort policy and provider capability","Offline access covers cached interface resources only","Accuracy depends on data entered by guests and staff"];
 bullets(s,left,75,165,530,420,20); bullets(s,right,675,165,530,420,20); footer(s,6);
}
// 7 significance
{
 const s=base("Significance of the study");
 const a=[["Management","Centralized operational and financial information, fewer manual errors and better reporting"],["Resort staff","Simpler reservations, payment verification, guest service and task notifications"],["Guests","Convenient booking, payment choices, confirmations, QR check-in and secure booking access"],["Administrators","Controlled user access, content management, security monitoring and backups"],["Researchers and developers","A reference for reservation systems, payment integration, QR codes and future expansion"]];
 a.forEach((q,i)=>{const y=155+i*98; text(s,q[0],75,y,245,55,20,i===0?C.coral:C.blue,true); text(s,q[1],330,y,850,60,19,C.ink,false); if(i<4) box(s,75,y+75,1105,1,"#D7E4E7","none","rect");}); footer(s,7);
}
// 8 features
{
 const s=base("Proposed features");
 const f=["Secure guest, receptionist and administrator access","Online and walk-in reservations","Room browsing and real-time availability","Calendar and guest record management","PayMongo, GCash and bank-deposit support","Promotions and statutory discounts","Email confirmation and QR check-in","Check-in, check-out, cancellation and refunds","Room, staff and website content management","Dashboards, reports, search and notifications","Activity logs and security monitoring","Database backup and restoration","Responsive design and PWA support"];
 bullets(s,f.slice(0,7),70,150,550,455,19); bullets(s,f.slice(7),660,150,550,455,19); footer(s,8);
}
// 9 requirements
{
 const s=base("Functional requirements");
 bullets(s,["Secure role-based login and verification","Guest room browsing, availability checks and booking access","Charge, discount, deposit and balance calculation","PayMongo processing plus staff-verified manual payments","Promotion and Senior Citizen or PWD discount management","Confirmations, email notifications and QR code generation","Walk-in booking, arrival, departure, cancellation, no-show and refund handling","Management of rooms, guests, staff, inquiries, reviews and site content","Calendars, dashboards, reports, logs and backups","Responsive access and PWA installation"],85,150,1110,470,21); footer(s,9);
}
// 10 nonfunctional
{
 const s=base("Non-functional requirements");
 const groups=[["Security","Password hashing, sessions, 2FA, role permissions, CSRF protection, validation and protected documents"],["Performance","Responsive requests, immediate inventory updates, optimized queries and compressed assets"],["Reliability","Duplicate-booking prevention, consistent transactions, backups and safe error handling"],["Usability","Clear navigation, validation messages, responsive layouts and visible statuses"],["Compatibility","Current standards-compliant browsers, XAMPP and compatible hosting environments"],["Maintainability","Separated components, protected configuration, controlled migrations and maintained documentation"]];
 groups.forEach((g,i)=>{const col=i%2,row=Math.floor(i/2),x=70+col*590,y=150+row*160; text(s,g[0],x,y,220,35,21,i%2?C.blue:C.coral,true); text(s,g[1],x,y+40,515,90,18,C.ink,false);}); footer(s,10);
}
// 11 modules
{
 const s=base("System modules");
 const mods=[["Login and authentication","Staff login, verification, password reset and role access"],["Guest booking access","Reference and email verification, OTP, status, QR, rebooking and refunds"],["Dashboard","Reservations, rooms, arrivals, payments, revenue and alerts"],["Reservations","Online and walk-in bookings, availability, calendar, updates and history"],["Payments and discounts","Payment methods, balances, promotions, discounts and refunds"],["Guest service","QR check-in, arrival/departure, reminders, inquiries and reviews"],["Administration","Rooms, pricing, staff, content, reporting, logs and backups"]];
 mods.forEach((m,i)=>{const col=i%2,row=Math.floor(i/2),x=65+col*600,y=145+row*125; box(s,x,y,550,100,i===6?C.sand:C.white,i===6?"none":C.aqua); text(s,m[0],x+20,y+12,510,28,19,C.navy,true); text(s,m[1],x+20,y+43,510,48,16,C.gray,false);}); footer(s,11);
}
// 12 database
{
 const s=base("Database structure");
 const groups=[["Users and access","Administrators, receptionists, guest/admin OTPs, password resets and rate limits"],["Booking and inventory","Bookings, room types and individual rooms"],["Payments and discounts","Payments, PayMongo checkouts, promotions, booking discounts, discount history and refunds"],["Communication and content","Notifications, inquiries, reviews, gallery and settings"],["Audit and protection","Activity logs and security logs"]];
 groups.forEach((g,i)=>{const y=150+i*96; text(s,g[0],75,y,270,48,19,[C.coral,C.blue,C.navy,C.blue,C.coral][i],true); text(s,g[1],355,y,825,55,18,C.ink,false);}); footer(s,12);
}
// 13 technology
{
 const s=base("Technology stack");
 const rows=[["Frontend","HTML5, CSS3, JavaScript"],["Backend","PHP and Python"],["Database","MySQL"],["Web server","Apache through XAMPP"],["Email","PHPMailer and SMTP"],["Payments","PayMongo API"],["Development","Visual Studio Code and Git"],["Browsers","Chrome, Edge and Firefox"]];
 rows.forEach((r,i)=>{const y=145+i*62; box(s,95,y,1090,52,i%2?C.pale:C.white,"none","rect"); text(s,r[0],120,y+5,280,42,18,C.blue,true); text(s,r[1],420,y+5,730,42,18,C.ink,false);}); footer(s,13);
}
// 14 methodology
{
 const s=base("Agile development methodology");
 const phases=[["1","Planning","Define problems, objectives, scope and requirements"],["2","Analysis","Study resort operations, users, payments and security"],["3","Design","Prepare architecture, database, interfaces and process flows"],["4","Development","Build guest, staff, booking, payment and reporting modules"],["5","Testing","Check functions, usability, security, compatibility and errors"],["6","Implementation","Deploy the system and configure services and users"],["7","Evaluation","Collect feedback, fix issues, monitor and improve"]];
 phases.forEach((v,i)=>{const x=45+i*175; box(s,x,215,150,245,i%2?C.sand:C.white,i%2?"none":C.aqua); text(s,v[0],x+45,232,60,45,27,C.coral,true,"center"); text(s,v[1],x+10,290,130,38,18,C.navy,true,"center"); text(s,v[2],x+14,338,122,105,15,C.gray,false,"center");});
 text(s,"Repeated review and improvement connect each phase back to planning.",260,520,760,50,21,C.blue,true,"center"); footer(s,14);
}
// 15 expected output + enhancements
{
 const s=base("Expected output and future enhancements");
 text(s,"Expected system",70,150,500,40,22,C.coral,true); bullets(s,["Accommodation, price, amenity and availability display","Online and walk-in reservations","Multiple payment methods and discount handling","Confirmations, QR codes and email notices","Arrival, departure, cancellation and refund workflows","Operational monitoring, reports, logs and backups"],70,200,520,350,18);
 text(s,"Future enhancements",650,150,500,40,22,C.blue,true); bullets(s,["Native mobile apps and SMS","Travel platform integrations","Chat support and AI room recommendations","Dynamic pricing and loyalty programs","Multi-resort management","Maps, transport and more payment gateways","Accounting integration and predictive analytics","Multilingual and biometric support","Expanded disaster recovery"],650,200,520,390,18); footer(s,15);
}
// 16-19 source flowcharts
const flowTitles=["Guest reservation and payment","Confirmed booking, check-in and check-out","Guest portal, cancellation and refund","Staff and administrator operations"];
for(let i=0;i<4;i++){
 const s=base(flowTitles[i],"SYSTEM FLOWCHART");
 const bytes=await fs.readFile(path.join(mediaDir,`image${i+1}.png`));
 s.images.add({blob:bytes,contentType:"image/png",alt:flowTitles[i]+" flowchart",fit:"contain",position:{left:55,top:125,width:1170,height:535}});
 footer(s,16+i);
}

const requirements={ explicitTotalSlideCount:20, requiredNativeTableOwnerSlides:[], requiredNativeChartOwnerSlides:[] };
const fontPolicy={basis:"design",families:[font]};
const stagingDir=path.join(workspaceDir,".codex-finalizer"); await fs.mkdir(stagingDir,{recursive:true});
const candidatePath=path.join(stagingDir,"santa-fe-expanded-candidate.pptx");
await (await PresentationFile.exportPptx(p)).save(candidatePath);
const result=await finalizePresentation({
 ...requirements, workspaceDir, candidatePath, finalPath:FINAL_PPTX, pythonExecutable:PYTHON,
 integrityValidatorPath:path.join(SKILL_DIR,"container_tools/inspect_presentation_package_integrity.py"),
 layoutValidatorPath:path.join(SKILL_DIR,"container_tools/inspect_presentation_layout_geometry.py"),
 layoutArgs:["--expected-slide-size-emu","12192000,6858000","--validate-bullet-geometry","--validate-heading-fit"],
 requiredNativeTableOwnerSlides:[], fontPolicy, verifyArtifactToolImport:true,
 receiptPath:path.join(stagingDir,"Santa_Fe_Beach_Club_Project_Presentation_Expanded.validation.json")
});
console.log(JSON.stringify({final:FINAL_PPTX,result},null,2));
