#include "onnxruntime_c_api.h"
#include <string.h>
/* Called only with a server-owned model path and fixed-sized buffers. */
int iw_segment(const void *api_table, const char *model, float *input, float *output) {
 const OrtApi *api=(const OrtApi *)api_table;
 if(!api) return 2;
 OrtEnv *env=NULL; OrtSessionOptions *options=NULL; OrtSession *session=NULL;
 OrtMemoryInfo *memory=NULL; OrtValue *value=NULL, *result=NULL; OrtStatus *error=NULL;
 int rc=1; float *data=NULL; const int64_t dims[]={1,3,320,320};
 const char *inputs[]={"input.1"}, *outputs[]={"1959"};
 #define CHECK(call) do { error=(call); if(error) goto done; } while(0)
 CHECK(api->CreateEnv(ORT_LOGGING_LEVEL_ERROR,"image-workbench",&env));
 CHECK(api->DisableTelemetryEvents(env));
 CHECK(api->CreateSessionOptions(&options));
 CHECK(api->SetIntraOpNumThreads(options,1));
 CHECK(api->SetInterOpNumThreads(options,1));
 CHECK(api->CreateSession(env,model,options,&session));
 CHECK(api->CreateCpuMemoryInfo(OrtArenaAllocator,OrtMemTypeDefault,&memory));
 CHECK(api->CreateTensorWithDataAsOrtValue(memory,input,3*320*320*sizeof(float),dims,4,ONNX_TENSOR_ELEMENT_DATA_TYPE_FLOAT,&value));
 CHECK(api->Run(session,NULL,inputs,(const OrtValue *const *)&value,1,outputs,1,&result));
 CHECK(api->GetTensorMutableData(result,(void **)&data));
 memcpy(output,data,320*320*sizeof(float));rc=0;
 done:
 if(error) api->ReleaseStatus(error);
 if(result) api->ReleaseValue(result);
 if(value) api->ReleaseValue(value);
 if(memory) api->ReleaseMemoryInfo(memory);
 if(session) api->ReleaseSession(session);
 if(options) api->ReleaseSessionOptions(options);
 if(env) api->ReleaseEnv(env);
 return rc;
}

#include <math.h>
/* Joint bilateral upsampling: align a 320px mask with original RGB edges. */
void iw_refine(const unsigned char *rgb,int width,int height,const unsigned char *guide,
               const float *prob,float cutoff,unsigned char *mask) {
 for(int y=0;y<height;y++) for(int x=0;x<width;x++) {
  const int p=y*width+x; const float fx=(x+.5f)*320/width-.5f,fy=(y+.5f)*320/height-.5f;
  const int cx=(int)floorf(fx+.5f),cy=(int)floorf(fy+.5f); float sum=0,weights=0;
  for(int dy=-2;dy<=2;dy++) for(int dx=-2;dx<=2;dx++) {
   int sx=cx+dx,sy=cy+dy;if(sx<0||sy<0||sx>=320||sy>=320)continue;
   int q=sy*320+sx;float diff=0;
   for(int c=0;c<3;c++) {float d=(float)rgb[p*3+c]-guide[q*3+c];diff+=d*d;}
   float spatial=(sx-fx)*(sx-fx)+(sy-fy)*(sy-fy);
   float weight=expf(-diff/(2*25*25)-spatial/(2*1.2f*1.2f))+.000000001f;
   sum+=prob[q]*weight;weights+=weight;
  }
  mask[p]=(sum/weights)>=cutoff;
 }
}
