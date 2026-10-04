import {cookies} from "next/headers"; import crypto from "crypto"; const C="k3909_session"; const s=()=>process.env.SESSION_SECRET||"dev-change-this-secret";
const sig=(x:string)=>crypto.createHmac("sha256",s()).update(x).digest("hex");
export async function createSession(userId:string){const t=Buffer.from(JSON.stringify({userId,exp:Date.now()+86400000})).toString("base64url");(await cookies()).set(C,`${t}.${sig(t)}`,{httpOnly:true,sameSite:"lax",secure:process.env.NODE_ENV==="production",path:"/",maxAge:86400})}
export async function destroySession(){(await cookies()).delete(C)}
export async function getSession(){const v=(await cookies()).get(C)?.value;if(!v)return null;const [t,h]=v.split(".");if(!t||sig(t)!==h)return null;try{const x=JSON.parse(Buffer.from(t,"base64url").toString());return x.exp>Date.now()?x:null}catch{return null}}
